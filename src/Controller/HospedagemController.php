<?php

namespace App\Controller;

use App\Entity\HospedagemCaes;
use App\Entity\Financeiro;
use App\Repository\HospedagemCaesRepository;
use App\Repository\FinanceiroRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/hospedagem")
 */
class HospedagemController extends DefaultController
{

    /**
     * @Route("/agendar", name="hospedagem_agendar", methods={"GET", "POST"})
     */
    public function agendar(Request $request): Response
    {
        $this->switchDB();
        $baseId = $request->getSession()->get('userId');

        if ($request->isMethod('POST')) {
            $hospedagem = new HospedagemCaes();
            $hospedagem->setClienteId($request->request->get('cliente_id'));
            $hospedagem->setPetId($request->request->get('pet_id'));
            $hospedagem->setDataEntrada(new \DateTime($request->request->get('dataEntrada')));
            $hospedagem->setDataSaida(new \DateTime($request->request->get('dataSaida')));

            $valorInformado = (float) $request->request->get('valor');
            $dias = $hospedagem->getDataSaida()->diff($hospedagem->getDataEntrada())->days + 1;
            $valorTotal = $dias * $valorInformado;

            $hospedagem->setValor($valorTotal);
            $hospedagem->setObservacoes($request->request->get('observacoes'));

            $this->getRepositorio(HospedagemCaes::class)->insert($baseId, $hospedagem);

            return $this->redirectToRoute('hospedagem_listar');
        }

        return $this->render('hospedagem/agendar.html.twig', [
            'clientes' => $this->getRepositorio(HospedagemCaes::class)->getClientes($baseId),
            'pets' => $this->getRepositorio(HospedagemCaes::class)->getPets($baseId),
        ]);
    }

    /**
     * @Route("/listar", name="hospedagem_listar", methods={"GET"})
     */
    public function listar(Request $request): Response
    {
        $this->switchDB();
        $baseId = $request->getSession()->get('userId');
        return $this->render('hospedagem/listar.html.twig', [
            'dados' => $this->getRepositorio(HospedagemCaes::class)->localizaTodos($baseId)
        ]);
    }

    /**
     * @Route("/deletar/{id}", name="hospedagem_deletar", methods={"POST"})
     */
    public function deletar(Request $request, int $id): Response
    {
        $this->switchDB();
        $baseId = $request->getSession()->get('userId');

        if (!$this->getRepositorio(HospedagemCaes::class)->localizaPorId($baseId, $id)) {
            throw $this->createNotFoundException('Hospedagem não encontrada');
        }

        $this->getRepositorio(HospedagemCaes::class)->delete($baseId, $id);
        return $this->redirectToRoute('hospedagem_listar');
    }

    /**
     * @Route("/editar/{id}", name="hospedagem_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $this->switchDB();
        $baseId = $request->getSession()->get('userId');
        $hospedagem = $this->getRepositorio(HospedagemCaes::class)->localizaPorId($baseId, $id);

        if (!$hospedagem) {
            throw $this->createNotFoundException('Hospedagem não encontrada.');
        }

        if ($request->isMethod('POST')) {
            $hospedagemObj = new HospedagemCaes();
            $hospedagemObj->setClienteId($request->request->get('cliente_id'));
            $hospedagemObj->setPetId($request->request->get('pet_id'));
            $hospedagemObj->setDataEntrada(new \DateTime($request->request->get('dataEntrada')));
            $hospedagemObj->setDataSaida(new \DateTime($request->request->get('dataSaida')));

            $valorInformado = (float) $request->request->get('valor');
            $dias = $hospedagemObj->getDataSaida()->diff($hospedagemObj->getDataEntrada())->days + 1;
            $valorTotal = $dias * $valorInformado;

            $hospedagemObj->setValor($valorTotal);
            $hospedagemObj->setObservacoes($request->request->get('observacoes'));
            $hospedagemObj->setId($id);

            $this->getRepositorio(HospedagemCaes::class)->update($baseId, $hospedagemObj);

            $this->addFlash('success', 'Hospedagem atualizada com sucesso!');
            return $this->redirectToRoute('hospedagem_listar');
        }

        return $this->render('hospedagem/editar.html.twig', [
            'hospedagem' => $hospedagem,
            'clientes' => $this->getRepositorio(HospedagemCaes::class)->getClientes($baseId),
            'pets' => $this->getRepositorio(HospedagemCaes::class)->getPets($baseId),
        ]);
    }


    /**
     * @Route("/pagar/{id}", name="hospedagem_concluir_pagamento", methods={"POST"})
     */
    public function concluirPagamento(Request $request, int $id): Response
    {
        $this->switchDB();
        $baseId = $request->getSession()->get('userId');
        $hospedagem = $this->getRepositorio(HospedagemCaes::class)->localizaPorId($baseId, $id);

        if (!$hospedagem) {
            throw $this->createNotFoundException('Hospedagem não encontrada.');
        }

        $financeiro = new Financeiro();
        $financeiro->setDescricao('Hospedagem do Pet');
        $financeiro->setValor($hospedagem['valor']);
        $financeiro->setData(new \DateTime());
        $financeiro->setPetId($hospedagem['pet_id']);

        $this->getRepositorio(Financeiro::class)->save($baseId, $financeiro);

        $this->addFlash('success', 'Pagamento registrado no financeiro.');

        return $this->redirectToRoute('hospedagem_listar');
    }
}