<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    

    /**
     * @Route("/dashboard/login", name="app_login")
     */
    public function login(Request $request): Response
    {
        $this->globalVariables();

        $this->session = $request->getSession();

        if ($this->session->get('login')) {
            return $this->redirectToRoute('dashboard_home');
        }

        return $this->render('dashboard/login/index.html.twig', $this->data);
    }

    /**
     * @Route("/dashboard/login/logar", name="app_logar")
     */
    public function doLogin(Request $request): Response
    {
        $json = [];

        $auth = new \App\Service\Auth();
        $json = $auth->attempt($request, $this->getRepositorio(Users::class));

        if (!empty($json->getResult())) {
            return $this->json($json);
        }

        if (!$auth->validaPasswd()) {
            $json['error'] = true;
            $json['message'] = 'Senha informada é inválida.';
            $json['status'] = 'danger';
            return $this->json($json, 500);
        }

        $request->getSession()->set('userId', $auth->getUser()->getId());
        $request->getSession()->set('user', $auth->getUser());
        $request->getSession()->set('login', true);

        $json = [];
        $json['error'] = false;
        $json['message'] = 'Login efetuado com sucesso, estamos te redirecinando.';
        $json['status'] = 'success';
        $json['redirect'] = $this->redirectToRoute('dashboard_home');
        return $this->redirectToRoute('dashboard_home');
        // return $this->json($json);

    }
}


