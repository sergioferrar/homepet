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
use Symfony\Component\Security\Core\Security;
use Doctrine\Persistence\ManagerRegistry;
use App\Service\DynamicConnectionManager;

class AccessDeniedListener implements EventSubscriberInterface
{
    protected $security;
    protected $request;
    private $urlGenerator;
    private $entityManager;
    private $router;
    private $managerRegistry;
    
    // Flag para prevenir recursão infinita
    private static $processing = false;

    public function __construct(EntityManagerInterface $entityManager, ?Security $security, UrlGeneratorInterface $urlGenerator, RouterInterface $router, ManagerRegistry $managerRegistry, RequestStack $request)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->urlGenerator = $urlGenerator;
        $this->router = $router;
        $this->request = $request->getCurrentRequest();
        $this->managerRegistry = $managerRegistry;
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
        // dd($exception);
        
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
        if (!$request->getSession()->has('login') && !$this->security->getUser()) {
            $mensagem = ($exception);//'';
            $param = ($mensagem != '' ? ['error' => $mensagem] : []);

            $url = $this->router->generate('logout', $param);
            $event->getRequest()->getSession()->save();
            $response = new RedirectResponse($url);
            $event->setResponse($response);
            $event->stopPropagation();
            return;
        }

        if (!$request->getSession()->has('login') && $this->security->getUser()) {
            $mensagem = ($exception);//'A-sua-sessão-expirou';
            // if ($this->security->getUser()->getStatus() == "Inativo") {
            //     $mensagem = 'Usuário-Inativo';
            // }
            $url = $this->router->generate('logout', ['error' => $mensagem]);
            $event->getRequest()->getSession()->save();
            $response = new RedirectResponse($url);
            $event->setResponse($response);
            $event->stopPropagation();
            return;
        }

        if (!$this->security->getUser()) {
            $mensagem = ($exception);//'A-sua-sessão-expirou';
            $url = $this->router->generate('logout', ['error' => $mensagem]);
            $event->getRequest()->getSession()->save();
            $response = new RedirectResponse($url);
            $event->setResponse($response);
            $event->stopPropagation();
            return;
        }

        // ============================================================
        // PULAR VALIDAÇÕES PARA SUPER ADMIN
        // ============================================================
        $user = $this->security->getUser();
        $roles = $user->getRoles();
        
        // // Se é Super Admin, NÃO valida plano nem estabelecimento
        if ($user->getAccessLevel() === 'Super Admin') {
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
        $petshopId = $user->getPetshopId();
        
        // Se não tem petshop_id, redireciona para login
        if ($petshopId === null) {
            $mensagem = ($exception);//'Usuário-sem-estabelecimento';
            $url = $this->router->generate('logout', ['error' => $mensagem]);
            $event->getRequest()->getSession()->save();
            $response = new RedirectResponse($url);
            $event->setResponse($response);
            $event->stopPropagation();
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
        } catch (\Exception $e) {
            // Se falhar ao buscar, apenas retornar
            return;
        }

        if (!$estabelecimento) {
            $mensagem = ($exception);//'Estabelecimento-não-encontrado';
            $url = $this->router->generate('logout', ['error' => $mensagem]);
            $event->getRequest()->getSession()->save();
            $response = new RedirectResponse($url);
            $event->setResponse($response);
            $event->stopPropagation();
            return;
        }

        // Validar plano
        $validaPlano = $this->verificarPlanoPorPeriodo(
            $estabelecimento->getDataPlanoInicio(), 
            $estabelecimento->getDataPlanoFim()
        );
        
        if ($validaPlano) {
            $mensagem = str_replace(' ', '-', $validaPlano);
            $url = $this->router->generate('logout', ['error' => $mensagem]);
            $event->getRequest()->getSession()->save();
            $response = new RedirectResponse($url);
            $event->setResponse($response);
            $event->stopPropagation();
            return;
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

        $habilitado = true;
        if (!empty($listaGrupo) && !in_array($this->security->getUser()->getIdGrupo(), $listaGrupo)) {
            throw new AccessDeniedException("Você não tem permissao de acesso a esta pagina");

            return false;
        }

        return true;
    }
}