<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UsuarioController extends AbstractController
{
    /**
     * @Route("/usuario/lista", name="app_usuario")
     */
    public function index(): Response
    {
        return $this->render('usuario/index.html.twig', [
            'controller_name' => 'UsuarioController',
        ]);
    }

    /**
     * @Route("/usuario/novo", name="app_usuario_create")
     */
    public function create(): Response
    {
        return $this->render('usuario/index.html.twig', [
            'controller_name' => 'UsuarioController',
        ]);
    }

    /**
     * @Route("/usuario/edit/{id}", name="app_usuario_edit")
     */
    public function edit(): Response
    {
        return $this->render('usuario/index.html.twig', [
            'controller_name' => 'UsuarioController',
        ]);
    }
}
