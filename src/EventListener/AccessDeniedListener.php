<?php

namespace App\EventListener;

use App\Entity\Menu;
use App\Service\DynamicConnectionManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;
use Symfony\Component\Security\Core\Security;

/**
 * Trata exceções de autenticação/autorização sem expor detalhes internos ao usuário.
 *
 * Importante:
 * - Nunca envie o objeto Throwable para a URL ou para a flash session.
 * - A ausência de um usuário autenticado não prova, sozinha, que a sessão expirou;
 *   por isso a mensagem informa que a autenticação expirou ou deixou de ser válida.
 * - Exceções que não são de autenticação/autorização não são interceptadas aqui.
 */
class AccessDeniedListener implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private Security $security;
    private RouterInterface $router;
    private ManagerRegistry $managerRegistry;
    private LoggerInterface $logger;

    /** Proteção adicional contra redirecionamentos recursivos durante a mesma execução. */
    private static bool $processing = false;

    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        RouterInterface $router,
        ManagerRegistry $managerRegistry,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->router = $router;
        $this->managerRegistry = $managerRegistry;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        // Prioridade baixa: deixa os listeners centrais do Symfony processarem primeiro.
        return [KernelEvents::EXCEPTION => ['onKernelException', -100]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (self::$processing) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Evita loops ao processar login/logout e rotas internas de depuração.
        $ignoredRoutes = ['logout', 'app_login', 'api_login', '_wdt', '_profiler'];
        if ($route && in_array($route, $ignoredRoutes, true)) {
            return;
        }

        $exception = $event->getThrowable();

        // A exceção relevante pode estar encadeada como previous (ex.: HttpException
        // contendo InsufficientAuthenticationException, que por sua vez veio do firewall).
        $securityException = $this->findSecurityException($exception);
        if ($securityException === null) {
            return;
        }

        self::$processing = true;
        try {
            $user = $this->security->getUser();
            $session = $request->hasSession() ? $request->getSession() : null;

            // Sem usuário autenticado: não tentamos inferir a causa exata. Pode ser
            // sessão expirada, sessão inválida ou acesso direto a uma rota protegida.
            if ($user === null) {
                $message = 'Sua sessão expirou ou sua autenticação não é mais válida. Entre novamente.';
                $this->logger->info('Acesso protegido sem usuário autenticado.', [
                    'route' => $route,
                    'path' => $request->getPathInfo(),
                    'exception' => get_class($securityException),
                ]);
                $this->redirectToLogin($event, $message);
                return;
            }

            // A partir daqui existe usuário autenticado. Não transformar qualquer
            // AccessDenied em logout antes de conferir as regras de plano/estabelecimento.
            if ($user->getAccessLevel() === 'Super Admin') {
                return;
            }

            // Super Admin em modo de impersonação não passa pelas validações do tenant.
            if ($session !== null && $session->get('impersonating_establishment', false)) {
                return;
            }

            // Só verificamos estabelecimento/plano no contexto de uma exceção de acesso.
            // A ausência de petshopId é apresentada como problema de configuração da conta.
            if (!$securityException instanceof AccessDeniedException) {
                return;
            }

            $petshopId = $user->getPetshopId();
            if ($petshopId === null) {
                $this->logger->warning('Usuário autenticado sem estabelecimento associado.', [
                    'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
                    'route' => $route,
                ]);
                $this->redirectToLogin($event, 'Sua conta não está vinculada a um estabelecimento. Entre em contato com o suporte.');
                return;
            }

            // Restaura a conexão principal antes de consultar o estabelecimento.
            try {
                (new DynamicConnectionManager($this->managerRegistry))->restoreOriginal();
                $estabelecimento = $this->entityManager
                    ->getRepository(\App\Entity\Estabelecimento::class)
                    ->find($petshopId);
            } catch (\Throwable $e) {
                // Falha técnica de banco não deve ser mascarada como erro de permissão.
                $this->logger->error('Falha ao verificar o estabelecimento durante tratamento de acesso negado.', [
                    'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
                    'exception' => $e,
                ]);
                return;
            }

            if (!$estabelecimento) {
                $this->logger->warning('Estabelecimento associado ao usuário não foi encontrado.', [
                    'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
                    'petshop_id' => $petshopId,
                ]);
                $this->redirectToLogin($event, 'Não foi possível localizar o estabelecimento vinculado à sua conta. Entre em contato com o suporte.');
                return;
            }

            $planMessage = $this->verificarPlanoPorPeriodo(
                $estabelecimento->getDataPlanoInicio(),
                $estabelecimento->getDataPlanoFim()
            );

            if ($planMessage !== false) {
                $this->logger->info('Acesso negado porque o plano do estabelecimento expirou.', [
                    'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
                    'petshop_id' => $petshopId,
                ]);
                $this->redirectToLogin($event, $planMessage);
                return;
            }

            // Usuário autenticado, plano válido e acesso negado: mensagem de autorização,
            // sem stack trace ou caminho de arquivo no conteúdo enviado ao navegador.
            $this->logger->notice('Usuário autenticado tentou acessar recurso sem permissão.', [
                'user_id' => method_exists($user, 'getId') ? $user->getId() : null,
                'route' => $route,
                'exception' => get_class($securityException),
            ]);
            $this->redirectToLogin($event, 'Você está autenticado, mas não tem permissão para acessar esta página.');
        } finally {
            self::$processing = false;
        }
    }

    /**
     * Procura uma exceção de segurança na cadeia de exceções.
     * O firewall pode encapsular a exceção original em uma HttpException.
     */
    private function findSecurityException(\Throwable $exception): ?\Throwable
    {
        $current = $exception;
        $visited = [];

        while ($current !== null) {
            $objectId = spl_object_id($current);
            if (isset($visited[$objectId])) {
                break;
            }
            $visited[$objectId] = true;

            if ($current instanceof AccessDeniedException || $current instanceof InsufficientAuthenticationException) {
                return $current;
            }

            $current = $current->getPrevious();
        }

        return null;
    }

    /**
     * Redireciona para a rota já usada pelo projeto e passa somente uma mensagem
     * amigável. A rota logout/login deve consumir o parâmetro "error" com segurança.
     */
    private function redirectToLogin(ExceptionEvent $event, string $message): void
    {
        $request = $event->getRequest();
        $url = $this->router->generate('logout', ['error' => $message]);
        $event->setResponse(new RedirectResponse($url));
        $event->stopPropagation();
    }

    /**
     * Retorna uma mensagem quando o plano já expirou; false quando está válido
     * ou quando não há datas suficientes para afirmar que expirou.
     */
    public function verificarPlanoPorPeriodo($dataInicio, $dataFim)
    {
        if ($dataInicio && $dataFim) {
            $hoje = new \DateTimeImmutable('today');
            $fim = $dataFim instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromInterface($dataFim)
                : new \DateTimeImmutable((string) $dataFim);

            if ($hoje > $fim->setTime(0, 0)) {
                return 'O plano do estabelecimento expirou em ' . $fim->format('d/m/Y') . '. Por favor, renove seu plano.';
            }
        }

        return false;
    }

    /**
     * Verifica se o grupo do usuário está entre os grupos permitidos para uma rota.
     * Mantido para compatibilidade com a lógica existente; este método só atua se chamado.
     */
    private function existRoute(?string $route): bool
    {
        if (!$route) {
            return false;
        }

        $permittedGroups = $this->entityManager->getRepository(Menu::class)->menuPermission($route);
        if (!$permittedGroups) {
            return false;
        }

        $allowedGroupIds = [];
        foreach ($permittedGroups as $row) {
            if (isset($row['idGrupo'])) {
                $allowedGroupIds[] = (int) $row['idGrupo'];
            }
        }

        $user = $this->security->getUser();
        if ($user && $allowedGroupIds && !in_array((int) $user->getIdGrupo(), $allowedGroupIds, true)) {
            throw new AccessDeniedException('Você não tem permissão de acesso a esta página.');
        }

        return true;
    }
}
