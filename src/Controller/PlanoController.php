<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PlanoController extends DefaultController
{
    /**
     * @Route("/plano/lista", name="app_plano")
     */
    public function index(): Response
    {
        return $this->render('plano/index.html.twig', [
            'controller_name' => 'PlanoController',
        ]);
    }

    /**
     * @Route("/plano/cadastrar", name="app_plano_create")
     */
    public function cadastrar(Request $request): Response
    {
        return $this->render('plano/cadastro.html.twig', [
            'controller_name' => 'PlanoController',
        ]);
    }

    public function strore(Request $request): Response{}
    
    public function editar(Request $request): Response{}
    
    public function update(Request $request): Response{}

    public function listarPlanosLoja(Request $request): Response{}
    public function inclurLoja(Request $request): Response{}// Incluir estabelecimento
    public function validarPlano(Request $request): Response{}
    public function ativarPlanoLoja(Request $request): Response{}
    public function inativarPlanoLoja(Request $request): Response{}
}
