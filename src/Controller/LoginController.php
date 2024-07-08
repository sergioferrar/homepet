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
     * @Route("/login", name="login")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Obter o erro de login, se houver
        $erro = $authenticationUtils->getLastAuthenticationError();

        // Último nome de usuário inserido pelo usuário
        $ultimoNomeUsuario = $authenticationUtils->getLastUsername();

        return $this->render('login/index.html.twig', ['ultimo_nome_usuario' => $ultimoNomeUsuario, 'erro' => $erro]);
    }

    
    /**
     * @Route("/logout", name="logout")
     */
    public function logout()
    {
        // O controlador pode ficar em branco: ele nunca será executado!
        throw new \Exception('Não se esqueça de ativar o logout em security.yaml');
    }
}


