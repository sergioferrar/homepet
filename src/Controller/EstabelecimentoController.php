<?php

namespace App\Controller;

use App\Entity\Estabelecimento;
use App\Entity\Usuario;
use App\Service\DatabaseBkp;
use App\Service\EmailService;
use App\Service\Payment\MercadoPagoService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @Route("dashboard")
 * */
class EstabelecimentoController extends DefaultController
{
    /**
     * @Route("/estabelecimento", name="app_estabelecimento")
     */
    public function index(): Response
    {
        if ($this->security->getUser()->getAccessLevel() == 'Admin') {
            return $this->redirectToRoute('petshop_edit', ['eid' => $this->getIdBase()]);
        }

        $estabelecimentos = $this->getRepositorio(\App\Entity\Estabelecimento::class)->listaEstabelecimentos($this->getIdBase());

        $estabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class)
            ->findById($this->security->getUser()->getPetshopId())[0];

        $validaPlano = $this->verificarPlanoPorPeriodo($estabelecimento->getDataPlanoInicio(), $estabelecimento->getDataPlanoFim());

        $data['estabelecimentos'] = $estabelecimentos;
        $data['validaPlano'] = $validaPlano;

        return $this->render('estabelecimento/index.html.twig', $data);
    }


    /**
     * @Route("/estabelecimento/renovacao/{eid}", name="petshop_renovacao")
     */
    public function renovaAssinatura(Request $request): Response
    {
        $dataAtual = new \DateTime();

        // Adiciona 30 dias
        $dataAtual->modify('+30 days');

        // Converte para string no formato do banco de dados (ex: MySQL)
        $dataFinal = $dataAtual->format('Y-m-d H:i:s'); // ou 'Y-m-d H:i:s' se precisar da hora

        $this->getRepositorio(\App\Entity\Estabelecimento::class)
            ->renovacao($this->getIdBase(), $request->get('eid'), (new \DateTime())->format('Y-m-d H:i:s'), $dataFinal);

        return $this->redirectToRoute('app_estabelecimento');
    }

    /**
     * @Route("/estabelecimento/editar/{eid}", name="petshop_edit")
     */
    public function editar(Request $request): Response
    {
        $loja = $this->getRepositorio(\App\Entity\Estabelecimento::class)->find($request->get('eid'));
        $data = [];
        $data['loja'] = $loja;
        return $this->render('estabelecimento/edit.html.twig', $data);
    }
}
