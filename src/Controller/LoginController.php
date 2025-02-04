<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends DefaultController
{


    /**
     * @Route("/dashboard/login", name="app_login")
     */
    public function login(AuthenticationUtils $authUtils, Request $request, $firewall = 'main'): Response
    {
        $error = $authUtils->getLastAuthenticationError();
        $lastUsername = $authUtils->getLastUsername();

        if ($request->get('error') && !empty($request->get('error'))) {
            $error = ucfirst(implode(' ', explode('-', $request->get('error'))));

            $this->addFlash('message', $error);

            // para remover a queryString de erro e exibir a mensagem apenas 1 única vez deve-se ter o redirecionamento para ela mesma
            return $this->redirectToRoute('app_login');
        }

        if ($this->security->getUser()) {
            return $this->redirectToRoute('app_login_valida');
        }
        // dd($request);
        $data = [];

        $data['login'] = null;
        $data['senha'] = null;
        $data['remember_me'] = null;

        $data['error'] = $error;
        $data['lastUsername'] = $lastUsername;

        if (isset($_COOKIE['remember_me'])) {
            $extract = explode('|', base64_decode($_COOKIE['remember_me']));
            $data['login'] = $extract[0];
            $data['senha'] = $extract[1];
            $data['remember_me'] = 'checked';
        }

        return $this->render('login/index.html.twig', $data);
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


