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
     * @Route("/login", name="app_login")
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
     * @Route("/valida-login", name="app_login_valida")
     */
    public function index(Request $request): Response
    {
        if (!$this->security->getUser()) {
            $request->getSession()->invalidate();
            return $this->redirectToRoute('app_login');
        }

//        if ($this->security->getUser()) {
//            if ($this->security->getUser()->getStatus() == "Inativo") {
//                $error = 'Usuário-Inativo';
//                throw new \Exception($error, 404);
//            }
//        }

        $request->getSession()->set('login', true);
        $request->getSession()->set('user', $this->security->getUser()->getNomeUsuario());
        $request->getSession()->set('accessLevel', $this->security->getUser()->getAccessLevel());
        $request->getSession()->set('userId', $this->security->getUser()->getId());
        $request->getSession()->set('estabelecimentoId', $this->security->getUser()->getPetshopId());

        $estabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class)
        ->findById($this->security->getUser()->getPetshopId())[0];

        $validaPlano = $this->verificarPlanoPorPeriodo($estabelecimento->getDataPlanoInicio(), $estabelecimento->getDataPlanoFim());
        //dd($validaPlano);
        if($validaPlano){
            $mensagem = str_replace(' ', '-', $validaPlano);
            return $this->redirectToRoute('logout', ['error' => $mensagem]);
        }
//        dd($request->getSession()->all());
        // dd($this->security->getUser());
        return $this->redirectToRoute('home');
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout(Request $request): Response
    {
        // Valida se existe sessão expired
        $request->getSession()->invalidate();
        return $this->redirectToRoute('app_login');
    }

    /**
     * @Route("/login/gerasenha/{senha}", name="app_gerasenha")
     */
    public function geraSenha(Request $request): Response
    {
        return $this->json(['senha' => password_hash($request->get('senha'), PASSWORD_DEFAULT, ["cost" => 10])]);
    }
}


