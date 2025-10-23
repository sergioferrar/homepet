<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

        if($request->get('confirmation')){
            $this->addFlash('message', base64_decode($request->get('confirmation')));
            return $this->redirectToRoute('app_login');
        }

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
     * @Route("/login/recupera-senha", name="app_login_recover")
     */
    public function recorver(EmailService $emailService, Request $request): Response
    {
        $data = [];

        if($request->isMethod('POST')){
            $usuario = $this->getRepositorio(\App\Entity\Usuario::class)->localizaUsuario($request->get('username'));
            
            if(!$usuario){
                $error = 'O e-mail informado não foi localizado.';
                $this->addFlash('message', $error);
                return $this->redirectToRoute('app_login_recover');
            }

            $token = base64_encode(json_encode([
                'username' => $request->get('username'), 
                'email' => $request->get('username'),
                'hash' => $usuario['senha'],
                'petshop_id' => $usuario['petshop_id']
            ]));
            
            $confirmationUrl = $this->generateUrl(
                'app_login_altera', 
                [
                    'token' => $token
                ], 
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            
            $html = $this->render('login/email.html.twig', [
                'confirmation_link' => $confirmationUrl,
                'nome_usuario' => $usuario['nome_usuario'],
            ])->getContent();

            $emailService->sendEmail(
                $request->get('username'),
                'Redefinição de senha solicitada',
                $html
            );
            
            $error = 'Um e-mail com o link para redefinir sua senha foi enviado para o endereço informado.<br>
Verifique sua caixa de entrada e siga as instruções para criar uma nova senha.<br>
Caso não encontre o e-mail, verifique também sua pasta de spam ou lixo eletrônico.';
                $this->addFlash('message', $error);
                return $this->redirectToRoute('app_login_recover');
            
        }

        return $this->render('login/recorver.html.twig', $data);
    }

    /**
     * @Route("/login/alterar-senha/{token}", name="app_login_altera")
     */
    public function doRecorver(EmailService $emailService, Request $request, EntityManagerInterface $em): Response
    {
        $token = $request->get('token');
        if (!$token) {
            $this->addFlash('error', 'Esta página não pode ser acessada diretamente.');
            return $this->redirectToRoute('app_login');
        }

        $tokenDecrypt = json_decode(base64_decode($token), true);

        $usuario = $this->getRepositorio(\App\Entity\Usuario::class)->localizaUsuario($tokenDecrypt['email']);
        if(!$usuario){
            $this->addFlash('error', 'Esta página não pode ser acessada diretamente sem um usuário válido.');
            return $this->redirectToRoute('app_login');
        }

        if($usuario['senha'] != $tokenDecrypt['hash']) {
            $this->addFlash('error', 'Esta página não pode ser acessada diretamente pois a hash não é valida.');
            return $this->redirectToRoute('app_login');
        }

        $data['token'] = $token;

        // validando post
        if($request->isMethod('POST')){

            $senha = $request->request->get('senha');
            $confirmar = $request->request->get('confirmar');

            // Validação básica
            if (empty($senha) || empty($confirmar)) {
                $this->addFlash('error', 'Por favor, preencha todos os campos.');
                return $this->redirect($request->getUri());
            }

            if ($senha !== $confirmar) {
                $this->addFlash('error', 'As senhas não coincidem.');
                return $this->redirect($request->getUri());
            }

            $user = $this->getRepositorio(\App\Entity\Usuario::class)->findOneBy(['email' => $tokenDecrypt['email']]);
            $hash = password_hash($senha, PASSWORD_DEFAULT, ['cost' => 10]);
            $user->setPassword($hash);
            $em->persist($user);
            $em->flush();

            $this->addFlash('message', 'Sua senha foi alterada com sucesso!');
            return $this->redirectToRoute('app_login');
        }


        return $this->render('login/dorecover.html.twig', $data);
    }

    /**
     * @Route("/login/gerasenha/{senha}", name="app_gerasenha")
     */
    public function geraSenha(Request $request): Response
    {
        return $this->json(['senha' => password_hash($request->get('senha'), PASSWORD_DEFAULT, ["cost" => 10])]);
    }
}
