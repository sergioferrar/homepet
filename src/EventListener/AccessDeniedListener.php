<?php

namespace App\EventListener;

use App\Entity\Menu;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\Persistence\ManagerRegistry;
use App\Service\DynamicConnectionManager;

class AccessDeniedListener implements EventSubscriberInterface
{
    // Flag para prevenir recursão infinita
    private static $processing = false;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RouterInterface $router,
        private readonly ManagerRegistry $managerRegistry,
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -100], // Prioridade baixa
        ];
    }

    public function onKernelException(ExceptionEvent $event)
    {
        // ============================================================
        // PREVENIR RECURSÃO INFINITA
        // ============================================================
        if (self::$processing) {
            // Já está processando, não fazer nada para evitar loop
            return;
        }

        self::$processing = true;

        try {
            $this->handleException($event);
        } finally {
            self::$processing = false;
        }
    }

    private function handleException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();
        $mensagem = '';

        // ============================================================
        // ROTAS QUE NÃO DEVEM SER INTERCEPTADAS
        // ============================================================
        $route = $request->attributes->get('_route');
        $ignoredRoutes = ['logout', 'app_login', 'api_login', '_wdt', '_profiler'];

        if (in_array($route, $ignoredRoutes)) {
            return; // Não interceptar essas rotas
        }

        // ============================================================
        // VERIFICAÇÃO DE SESSÃO E USUÁRIO
        // ============================================================

        $usuario = null;
        $token = $this->tokenStorage->getToken();

        if ($token !== null) {
            $usuario = $token->getUser();
        }

        if (!$request->getSession()->has('login') && !$usuario) {
            $mensagem = ($exception) ? $exception->getMessage() : '';
            $param = ($mensagem != '' ? ['error' => $mensagem] : []);

            try {
                $url = $this->router->generate('logout', $param);
                $event->getRequest()->getSession()->save();
                $response = new RedirectResponse($url);
                $event->setResponse($response);
                $event->stopPropagation();
            } catch (\Exception) {
                // Ignorar erro ao redirecionar
            }
            return;
        }

        if (!$request->getSession()->has('login') && $usuario) {
            $mensagem = ($exception) ? $exception->getMessage() : '';
            try {
                $url = $this->router->generate('logout', ['error' => $mensagem]);
                $event->getRequest()->getSession()->save();
                $response = new RedirectResponse($url);
                $event->setResponse($response);
                $event->stopPropagation();
            } catch (\Exception) {
                // Ignorar erro ao redirecionar
            }
            return;
        }

        if (!$usuario) {
            $mensagem = ($exception) ? $exception->getMessage() : '';
            try {
                $url = $this->router->generate('logout', ['error' => $mensagem]);
                $event->getRequest()->getSession()->save();
                $response = new RedirectResponse($url);
                $event->setResponse($response);
                $event->stopPropagation();
            } catch (\Exception) {
                // Ignorar erro ao redirecionar
            }
            return;
        }

        // ============================================================
        // PULAR VALIDAÇÕES PARA SUPER ADMIN
        // ============================================================
        $user = $usuario;

        // Se é Super Admin, NÃO valida plano nem estabelecimento
        if (method_exists($user, 'getAccessLevel') && $user->getAccessLevel() === 'Super Admin') {
            return;
        }

        // ============================================================
        // VERIFICAR IMPERSONATION
        // ============================================================
        $impersonating = $request->getSession()->get('impersonating_establishment', false);
        if ($impersonating) {
            // Super Admin acessando como estabelecimento, não validar
            return;
        }

        // ============================================================
        // VALIDAÇÕES APENAS PARA USUÁRIOS NORMAIS
        // ============================================================
        if (!method_exists($user, 'getPetshopId')) {
            return;
        }

        $petshopId = $user->getPetshopId();

        // Se não tem petshop_id, redireciona para login
        if ($petshopId === null) {
            $mensagem = 'Usuário sem estabelecimento';
            try {
                $url = $this->router->generate('logout', ['error' => $mensagem]);
                $event->getRequest()->getSession()->save();
                $response = new RedirectResponse($url);
                $event->setResponse($response);
                $event->stopPropagation();
            } catch (\Exception) {
                // Ignorar erro ao redirecionar
            }
            return;
        }

        // Restaurar conexão original antes de buscar
        try {
            (new DynamicConnectionManager($this->managerRegistry))->restoreOriginal();
        } catch (\Exception $e) {
            // Se falhar ao restaurar conexão, apenas retornar
            return;
        }

        // Buscar estabelecimento
        try {
            $estabelecimento = $this->entityManager
                ->getRepository(\App\Entity\Estabelecimento::class)
                ->findOneById($petshopId);
        } catch (\Exception) {
            // Se falhar ao buscar, apenas retornar
            return;
        }

        if (!$estabelecimento) {
            $mensagem = 'Estabelecimento não encontrado';
            try {
                $url = $this->router->generate('logout', ['error' => $mensagem]);
                $event->getRequest()->getSession()->save();
                $response = new RedirectResponse($url);
                $event->setResponse($response);
                $event->stopPropagation();
            } catch (\Exception) {
                // Ignorar erro ao redirecionar
            }
            return;
        }

        // Validar plano
        if (method_exists($estabelecimento, 'getDataPlanoInicio') && method_exists($estabelecimento, 'getDataPlanoFim')) {
            $validaPlano = $this->verificarPlanoPorPeriodo(
                $estabelecimento->getDataPlanoInicio(),
                $estabelecimento->getDataPlanoFim()
            );

            if ($validaPlano) {
                $mensagem = str_replace(' ', '-', $validaPlano);
                try {
                    $url = $this->router->generate('logout', ['error' => $mensagem]);
                    $event->getRequest()->getSession()->save();
                    $response = new RedirectResponse($url);
                    $event->setResponse($response);
                    $event->stopPropagation();
                } catch (\Exception) {
                    // Ignorar erro ao redirecionar
                }
                return;
            }
        }

        // Se não é AccessDeniedException, não fazer nada
        if (!$exception instanceof AccessDeniedException) {
            return;
        }
    }

    public function verificarPlanoPorPeriodo($dataInicio, $dataFim)
    {
        if ($dataInicio && $dataFim) {
            $hoje = new \DateTime();

            if ($hoje > $dataFim) {
                return "Seu plano expirou em " . $dataFim->format('d/m/Y') . ". Por favor, renove seu plano.";
            }
        }

        return false;
    }

    protected function redirect(string $url, int $status = 301): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    private function existRoute($route)
    {
        $repositoryMenu = $this->entityManager->getRepository(Menu::class);
        $repositoryUsuario = $this->entityManager->getRepository(Usuario::class);

        if (!$route) {
            return false;
        }

        $listaPermissaoPorRota = $repositoryMenu->menuPermission($route);
        if (!$listaPermissaoPorRota) {
            return false;
        }

        $listaGrupo = [];
        foreach ($listaPermissaoPorRota as $row) {
            $listaGrupo[$row['idGrupo']] = (int)$row['idGrupo'];
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            throw new AccessDeniedException("Você não tem permissão de acesso a esta página");
        }

        $usuario = $token->getUser();
        $habilitado = true;
        if (!empty($listaGrupo) && !in_array($usuario->getIdGrupo(), $listaGrupo)) {
            throw new AccessDeniedException("Você não tem permissão de acesso a esta página");
            return false;
        }

        return true;
    }
}