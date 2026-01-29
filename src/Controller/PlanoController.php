<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard")
 */
class PlanoController extends DefaultController
{
    /**
     * @Route("/plano/lista", name="app_plano")
     */
    public function index(): Response
    {
        $data = [];

        $planos = $this->getRepositorio(\App\Entity\Plano::class)->listaPlanos();
        $estabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class)->find($this->getIdBase());
        $validaPlano = $this->verificarPlanoPorPeriodo($estabelecimento->getDataPlanoInicio(), $estabelecimento->getDataPlanoFim());

        $data['planos'] = $planos;

        return $this->render('plano/index.html.twig', $data);
    }

    /**
     * @Route("/plano/cadastrar", name="app_plano_create")
     */
    public function cadastrar(Request $request): Response
    {
        $data = [];
        $data['modulos'] = $this->modulosSistema;
        return $this->render('plano/cadastro.html.twig', $data);
    }

    /**
     * @Route("/plano/editar/{id}", name="app_plano_editar")
     */
    public function editar(Request $request): Response
    {
        $plano = $this->getRepositorio(\App\Entity\Plano::class)->verPlano($request->get('id'));

        $data['plano'] = $plano;
        $data['modulos'] = $this->modulosSistema;
        return $this->render('plano/editar.html.twig', $data);
    }

    /**
     * @Route("/plano/cadastrar/novo", name="app_plano_create_new")
     */
    public function strore(Request $request): Response
    {

        $plano = new \App\Entity\Plano();
        $plano->setTitulo($request->get('nome'));
        // $plano->setDescricao($request->get('descricao'));
        $plano->setDescricao(json_encode($request->get('modulos')));
        $plano->setValor($request->get('valor'));
        $plano->setStatus($request->get('status'));
        $plano->setTrial(($request->get('trial') ? true : false));
        $plano->setDataPlano((new \Datetime("now")));
        $plano->setModulos(json_encode($request->get('modulos')));

        $this->getRepositorio(\App\Entity\Plano::class)->add($plano, true);

        return $this->redirectToRoute('app_plano');
    }

    /**
     * @Route("/plano/editar/update/{id}", name="app_plano_update")
     */
    public function update(Request $request): Response
    {

        $plano = new \App\Entity\Plano();
        $plano->setTitulo($request->get('nome'));
        // $plano->setDescricao(addslashes($request->get('descricao')));
        $plano->setDescricao(json_encode($request->get('modulos')));
        $plano->setValor($request->get('valor'));
        $plano->setStatus($request->get('status'));
        $plano->setTrial(($request->get('trial') ? true : false));
        $plano->setDataPlano((new \Datetime("now")));
        $plano->setModulos(json_encode($request->get('modulos')));

        $this->getRepositorio(\App\Entity\Plano::class)->update($plano, $request->get('id'));

        return $this->redirectToRoute('app_plano');
    }

    public function listarPlanosLoja(Request $request): Response
    {
    }

    public function inclurLoja(Request $request): Response
    {
    }// Incluir estabelecimento

    public function validarPlano(Request $request): Response
    {
    }

    public function ativarPlanoLoja(Request $request): Response
    {
    }

    public function inativarPlanoLoja(Request $request): Response
    {
    }
}
