<?php

namespace App\Controller;

use App\Entity\HospedagemCaes;
use App\Entity\Financeiro;
use App\Repository\HospedagemCaesRepository;
use App\Repository\FinanceiroRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/hospedagem")
 */
class HospedagemController extends AbstractController
{
    private $repo;
    private $financeiroRepo;

    public function __construct(HospedagemCaesRepository $repo, FinanceiroRepository $financeiroRepo)
    {
        $this->repo = $repo;
        $this->financeiroRepo = $financeiroRepo;
    }

    /**
     * @Route("/agendar", name="hospedagem_agendar", methods={"GET", "POST"})
     */
    public function agendar(Request $req): Response
    {
        $baseId = $req->getSession()->get('userId');

        if ($req->isMethod('POST')) {
            $hospedagem = new HospedagemCaes();
            $hospedagem->setClienteId($req->request->get('cliente_id'));
            $hospedagem->setPetId($req->request->get('pet_id'));
            $hospedagem->setDataEntrada(new \DateTime($req->request->get('dataEntrada')));
            $hospedagem->setDataSaida(new \DateTime($req->request->get('dataSaida')));

            $valorInformado = (float) $req->request->get('valor');
            $dias = $hospedagem->getDataSaida()->diff($hospedagem->getDataEntrada())->days + 1;
            $valorTotal = $dias * $valorInformado;

            $hospedagem->setValor($valorTotal);
            $hospedagem->setObservacoes($req->request->get('observacoes'));

            $this->repo->insert($baseId, $hospedagem);

            return $this->redirectToRoute('hospedagem_listar');
        }

        return $this->render('hospedagem/agendar.html.twig', [
            'clientes' => $this->repo->getClientes($baseId),
            'pets' => $this->repo->getPets($baseId),
        ]);
    }

    /**
     * @Route("/listar", name="hospedagem_listar", methods={"GET"})
     */
    public function listar(Request $req): Response
    {
        $baseId = $req->getSession()->get('userId');
        return $this->render('hospedagem/listar.html.twig', [
            'dados' => $this->repo->findAll($baseId)
        ]);
    }

    /**
     * @Route("/deletar/{id}", name="hospedagem_deletar", methods={"POST"})
     */
    public function deletar(Request $req, int $id): Response
    {
        $baseId = $req->getSession()->get('userId');

        if (!$this->repo->findById($baseId, $id)) {
            throw $this->createNotFoundException('Hospedagem não encontrada');
        }

        $this->repo->delete($baseId, $id);
        return $this->redirectToRoute('hospedagem_listar');
    }

    /**
     * @Route("/editar/{id}", name="hospedagem_editar", methods={"GET", "POST"})
     */
    public function editar(Request $req, int $id): Response
    {
        $baseId = $req->getSession()->get('userId');
        $hospedagem = $this->repo->findById($baseId, $id);

        if (!$hospedagem) {
            throw $this->createNotFoundException('Hospedagem não encontrada.');
        }

        if ($req->isMethod('POST')) {
            $hospedagemObj = new HospedagemCaes();
            $hospedagemObj->setClienteId($req->request->get('cliente_id'));
            $hospedagemObj->setPetId($req->request->get('pet_id'));
            $hospedagemObj->setDataEntrada(new \DateTime($req->request->get('dataEntrada')));
            $hospedagemObj->setDataSaida(new \DateTime($req->request->get('dataSaida')));

            $valorInformado = (float) $req->request->get('valor');
            $dias = $hospedagemObj->getDataSaida()->diff($hospedagemObj->getDataEntrada())->days + 1;
            $valorTotal = $dias * $valorInformado;

            $hospedagemObj->setValor($valorTotal);
            $hospedagemObj->setObservacoes($req->request->get('observacoes'));
            $hospedagemObj->setId($id);

            $this->repo->update($baseId, $hospedagemObj);

            $this->addFlash('success', 'Hospedagem atualizada com sucesso!');
            return $this->redirectToRoute('hospedagem_listar');
        }

        return $this->render('hospedagem/editar.html.twig', [
            'hospedagem' => $hospedagem,
            'clientes' => $this->repo->getClientes($baseId),
            'pets' => $this->repo->getPets($baseId),
        ]);
    }


    /**
     * @Route("/pagar/{id}", name="hospedagem_concluir_pagamento", methods={"POST"})
     */
    public function concluirPagamento(Request $req, int $id): Response
    {
        $baseId = $req->getSession()->get('userId');
        $hospedagem = $this->repo->findById($baseId, $id);

        if (!$hospedagem) {
            throw $this->createNotFoundException('Hospedagem não encontrada.');
        }

        $financeiro = new Financeiro();
        $financeiro->setDescricao('Hospedagem do Pet');
        $financeiro->setValor($hospedagem['valor']);
        $financeiro->setData(new \DateTime());
        $financeiro->setPetId($hospedagem['pet_id']);

        $this->financeiroRepo->save($baseId, $financeiro);

        $this->addFlash('success', 'Pagamento registrado no financeiro.');

        return $this->redirectToRoute('hospedagem_listar');
    }
}