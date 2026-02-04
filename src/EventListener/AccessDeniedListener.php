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

class AccessDeniedListener implements EventSubscriberInterface
{
    protected $security;
    protected $request;
    private $urlGenerator;
    private $entityManager;
    private $router;

    public function __construct(EntityManagerInterface $entityManager, ?Security $security, UrlGeneratorInterface $urlGenerator, RouterInterface $router, RequestStack $request)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->urlGenerator = $urlGenerator;
        $this->router = $router;
        $this->request = $request->getCurrentRequest();

    }

    public static function getSubscribedEvents()
    {
        return [
            // the priority must be greater than the Security HTTP
            // ExceptionListener, to make sure it's called before
            // the default exception listener
            KernelEvents::EXCEPTION => ['onKernelException'],
            // KernelEvents::CONTROLLER_ARGUMENTS => ['onControllerException'],
            // KernelEvents::HANDLE => ['handle'],
        ];
    }

//    public function onControllerException(ControllerArgumentsEvent $eventController)
//    {
//        /**
//         * CRUZAR AS INFORMAÇÕES DO GRUPO DE ACESSO COM O MENU ACESSADO
//         * USAR O IDGRUPO PARA COMPARAR COM O IDGRUPO DO USUARIO X IDGRUPO_MENU
//         * SE O MENU ACESSADO NÃO TIVER A ROTA CADASTRADA, FAZER VALIDAÇÃO
//         * COM O MENU PAI
//         */
//        // echo "onControllerException";
//        $controller = $eventController->getController();
//        $request = $eventController->getRequest();
//        // $currentPageRole = $controller[0];
//        $route = $request->attributes->get('_route');
//
//        return !$this->existRoute($route);
//    }

    public function onKernelException(ExceptionEvent $event)
    {
        // echo "onKernelException"; exit;
        $exception = $event->getThrowable();
        // dd($exception);
        $request = $event->getRequest();
        $mensagem = '';


        if (!$request->getSession()->has('login') && !$this->security->getUser()) {
            $mensagem = '';//str_replace(' ', '-', 'Sua sessão expirou');
            $param = ($mensagem != ''?['error' => $mensagem]:[]);

            $url = $this->router->generate('logout', $param);
            $event->getRequest()->getSession()->save();
            $response = new RedirectResponse($url);
            $event->setResponse($response);
            $event->stopPropagation();
            return $response;
        }

        if (!$request->getSession()->has('login') && $this->security->getUser()) {
            $mensagem = 'A-sua-sessão-expirou';
            if ($this->security->getUser()->getStatus() == "Inativo") {
                $mensagem = 'Usuário-Inativo';
            }
            $url = $this->router->generate('logout', ['error' => $mensagem]);
            $event->getRequest()->getSession()->save();
            $response = new RedirectResponse($url);
            $event->setResponse($response);
            $event->stopPropagation();
            return $response;
        }

        if (!$this->security->getUser()) {
            $mensagem = 'A-sua-sessão-expirou';
            $url = $this->router->generate('logout', ['error' => $mensagem]);
            $event->getRequest()->getSession()->save();
            $response = new RedirectResponse($url);
            $event->setResponse($response);
            $event->stopPropagation();
            return $response;
        }

        $estabelecimento = $this->entityManager
        ->getRepository(\App\Entity\Estabelecimento::class)
        ->findOneById($this->security->getUser()->getPetshopId());

        $validaPlano = $this->verificarPlanoPorPeriodo($estabelecimento->getDataPlanoInicio(), $estabelecimento->getDataPlanoFim());
        // dd($estabelecimento);
        
        if($validaPlano){

            $mensagem = str_replace(' ', '-', $validaPlano);
            $url = $this->router->generate('logout', ['error' => $mensagem]);
            $event->getRequest()->getSession()->save();
            $response = new RedirectResponse($url);
            $event->setResponse($response);
            $event->stopPropagation();
            return $response;
        }

        if (!$exception instanceof AccessDeniedException && $mensagem !== '') {
            $url = $this->router->generate('error_show', [$exception]);
            $event->getRequest()->getSession()->save();
            $response = new RedirectResponse($url);
            $event->setResponse($response);
            $event->stopPropagation();
            return $response;
        }

        // $event->getRequest()->getSession()->save();

        // dd($event, $request, $url, $mensagem, $exception);
        // ... perform some action (e.g. logging)

        // optionally set the custom response
        // $event->setResponse($this->redirectToRoute('logout'), 403);

        // or stop propagation (prevents the next exception listeners from being called)
    }

    public function verificarPlanoPorPeriodo($dataInicio, $dataFim)
    {
        if($dataInicio && $dataFim){

            $hoje = new \DateTime();

            // dd($dataInicio, $dataFim    );
            if ($hoje > $dataFim) {
                return "Seu plano expirou em " . $dataFim->format('d/m/Y') . ". Por favor, renove seu plano.";
            } 
        }

        return false;
    }

    /**
     * Returns a RedirectResponse to the given URL.
     */
    protected function redirect(string $url, int $status = 301): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    ## Existencia da rota - 404
    ## Grupo de acesso da rota - 403
    ## Grupo de acesso do usuario na rota - 403
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
