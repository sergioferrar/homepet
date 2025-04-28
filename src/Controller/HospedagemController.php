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
            $clienteId = $request->request->get('cliente_id');
            if (!$clienteId) {
                throw new \InvalidArgumentException('Cliente não selecionado.');
            }

            $hospedagem = new HospedagemCaes();
            $hospedagem->setClienteId((int) $clienteId);
            $hospedagem->setPetId((int) $request->request->get('pet_id'));
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

        $dataFiltro = $request->query->get('data');
        if ($dataFiltro) {
            $dataFiltro = new \DateTime($dataFiltro);
            $dados = $this->getRepositorio(HospedagemCaes::class)->localizaPorData($baseId, $dataFiltro);
        } else {
            $dados = $this->getRepositorio(HospedagemCaes::class)->localizaTodos($baseId);
        }

        return $this->render('hospedagem/listar.html.twig', [
            'dados' => $dados,
            'dataFiltro' => $dataFiltro ? $dataFiltro->format('Y-m-d') : null
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
        $dados = $this->getRepositorio(HospedagemCaes::class)->localizaPorId($baseId, $id);

        if (!$dados) {
            throw $this->createNotFoundException('Hospedagem não encontrada.');
        }

        if ($request->isMethod('POST')) {
            $clienteId = $request->request->get('cliente_id');
            if (!$clienteId) {
                throw new \InvalidArgumentException('Cliente não selecionado.');
            }

            $hospedagem = new HospedagemCaes();
            $hospedagem->setClienteId((int) $clienteId);
            $hospedagem->setPetId((int) $request->request->get('pet_id'));
            $hospedagem->setDataEntrada(new \DateTime($request->request->get('dataEntrada')));
            $hospedagem->setDataSaida(new \DateTime($request->request->get('dataSaida')));

            $valorInformado = (float) $request->request->get('valor');
            $dias = $hospedagem->getDataSaida()->diff($hospedagem->getDataEntrada())->days + 1;
            $valorTotal = $dias * $valorInformado;

            $hospedagem->setValor($valorTotal);
            $hospedagem->setObservacoes($request->request->get('observacoes'));

            $this->getRepositorio(HospedagemCaes::class)->updateHospedagem($baseId, $id, $hospedagem);

            $this->addFlash('success', 'Hospedagem atualizada com sucesso!');
            return $this->redirectToRoute('hospedagem_listar');
        }

        return $this->render('hospedagem/editar.html.twig', [
            'hospedagem' => $dados,
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
        $dados = $this->getRepositorio(HospedagemCaes::class)->localizaPorId($baseId, $id);

        if (!$dados) {
            throw $this->createNotFoundException('Hospedagem não encontrada.');
        }

        // Verifica se já existe pagamento registrado
        $existePagamento = $this->getRepositorio(Financeiro::class)->verificaPagamentoExistente($baseId, $dados['pet_id'], $dados['valor'], $dados['data_entrada']);

        if ($existePagamento) {
            $this->addFlash('warning', 'Pagamento já foi realizado anteriormente.');
            return $this->redirectToRoute('hospedagem_listar');
        }

        $hospedagem = new HospedagemCaes();
        $hospedagem->setPetId($dados['pet_id']);
        $hospedagem->setValor($dados['valor']);

        $this->getRepositorio(HospedagemCaes::class)->registrarFinanceiro($baseId, $hospedagem);

        $this->addFlash('success', 'Pagamento registrado no financeiro.');

        return $this->redirectToRoute('hospedagem_listar');
    }


}
