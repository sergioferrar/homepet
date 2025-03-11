<?php

namespace App\Controller;

use App\Entity\Agendamento;
use App\Entity\Financeiro;
use App\Entity\FinanceiroPendente;
use App\Entity\Servico;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/agendamento")
 */
class AgendamentoController extends DefaultController
{
    /**
     * @Route("/", name="agendamento_index", methods={"GET"})
     */
    public function index(Request $request): Response
    {
        $data = $request->query->get('data') ? new \DateTime($request->query->get('data')) : new \DateTime();
        $agendamentos = $this->getRepositorio(Agendamento::class)->findByDate($this->session->get('userId'), $data);
        $totalAgendamentos = $this->getRepositorio(Agendamento::class)->contarAgendamentosPorData($this->session->get('userId'), $data);

        return $this->render('agendamento/index.html.twig', [
            'agendamentos' => $agendamentos,
            'data' => $data,
            'totalAgendamentos' => $totalAgendamentos,
        ]);
    }

    /**
     * @Route("/novo", name="agendamento_novo", methods={"GET", "POST"})
     */
    public function novo(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $agendamento = new Agendamento();
            $agendamento->setData(new \DateTime($request->get('data')));
            $agendamento->setPetId($request->get('pet_id'));
            $agendamento->setServicoId($request->get('servico_id'));
            $agendamento->setMetodoPagamento($request->get('metodo_pagamento', 'pendente'));
            $agendamento->setTaxiDog((bool)$request->get('taxi_dog'));
            $agendamento->setTaxaTaxiDog($request->get('taxa_taxi_dog') ?: null);
            $agendamento->setConcluido(false);

            if ($horaChegada = $request->get('hora_chegada')) {
                $agendamento->setHoraChegada(new \DateTime($request->get('data') . ' ' . $horaChegada));
            }

            $this->getRepositorio(Agendamento::class)->save($this->session->get('userId'), $agendamento);

            return $this->redirectToRoute('agendamento_index', ['data' => $agendamento->getData()->format('Y-m-d')]);
        }

        return $this->render('agendamento/novo.html.twig', [
            'pets' => $this->getRepositorio(Agendamento::class)->findAllPets($this->session->get('userId')),
            'servicos' => $this->getRepositorio(Agendamento::class)->findAllServicos($this->session->get('userId')),
        ]);
    }

    /**
     * @Route("/editar/{id}", name="agendamento_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $repo = $this->getRepositorio(Agendamento::class);
        $dados = $repo->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (!$dados) {
            throw $this->createNotFoundException('O agendamento não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $agendamento = new Agendamento();
            $agendamento->setId($id);
            $agendamento->setData(new \DateTime($request->get('data')));
            $agendamento->setPetId($request->get('pet_id'));
            $agendamento->setServicoId($request->get('servico_id'));
            $agendamento->setConcluido((bool)$request->get('concluido'));
            $agendamento->setMetodoPagamento($dados['metodo_pagamento']);

            $repo->update($this->session->get('userId'), $agendamento);

            return $this->redirectToRoute('agendamento_index', ['data' => $agendamento->getData()->format('Y-m-d')]);
        }

        return $this->render('agendamento/editar.html.twig', [
            'agendamento' => $dados,
            'pets' => $repo->findAllPets($this->session->get('userId')),
            'servicos' => $repo->findAllServicos($this->session->get('userId')),
        ]);
    }

    /**
     * @Route("/deletar/{id}", name="agendamento_deletar", methods={"POST"})
     */
    public function deletar(int $id): Response
    {
        $this->getRepositorio(Agendamento::class)->delete($this->session->get('userId'), $id);
        return $this->redirectToRoute('agendamento_index');
    }

    /**
     * @Route("/concluir/{id}", name="agendamento_concluir", methods={"POST"})
     */
    public function concluir(int $id): Response
    {
        $repo = $this->getRepositorio(Agendamento::class);
        $dados = $repo->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (!$dados) {
            throw $this->createNotFoundException('O agendamento não foi encontrado');
        }

        $servico = $this->getRepositorio(Servico::class)->listaServicoPorId($this->session->get('userId'), $dados['servico_id']);

        $financeiro = new Financeiro();
        $financeiro->setDescricao('Serviço para o pet: ' . $dados['pet_id']);
        $financeiro->setValor($servico['valor']);
        $financeiro->setData(new \DateTime());
        $financeiro->setPetId($dados['pet_id']);

        if ($dados['taxi_dog']) {
            $financeiro->setValor($financeiro->getValor() + $dados['taxa_taxi_dog']);
            $financeiro->setDescricao($financeiro->getDescricao() . ' + Táxi Dog');
        }

        $this->getRepositorio(Financeiro::class)->save($this->session->get('userId'), $financeiro);

        $agendamento = new Agendamento();
        $agendamento->setId($id);
        $agendamento->setData(new \DateTime($dados['data']));
        $agendamento->setPetId($dados['pet_id']);
        $agendamento->setServicoId($dados['servico_id']);
        $agendamento->setConcluido(true);
        $agendamento->setMetodoPagamento($dados['metodo_pagamento']);
        $repo->update($this->session->get('userId'), $agendamento);

        return $this->redirectToRoute('agendamento_index');
    }

    /**
     * @Route("/alterar-pagamento/{id}", name="agendamento_alterar_pagamento", methods={"POST"})
     */
    public function alterarPagamento(int $id, Request $request): Response
    {
        $repo = $this->getRepositorio(Agendamento::class);
        $dados = $repo->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (!$dados) {
            throw $this->createNotFoundException('Agendamento não encontrado.');
        }

        $metodoPagamento = $request->get('metodo_pagamento');

        $metodosPermitidos = [
            'dinheiro', 'pix', 'credito', 'debito', 'pendente',
            'pacote_semanal', 'pacote_semanal_1', 'pacote_semanal_2', 'pacote_semanal_3', 'pacote_semanal_4',
            'pacote_quinzenal'
        ];

        if (!in_array($metodoPagamento, $metodosPermitidos, true)) {
            throw new \InvalidArgumentException('Método de pagamento inválido: ' . $metodoPagamento);
        }

        $agendamento = new Agendamento();
        $agendamento->setId($id);
        $agendamento->setData(new \DateTime($dados['data']));
        $agendamento->setPetId($dados['pet_id']);
        $agendamento->setServicoId($dados['servico_id']);
        $agendamento->setConcluido((bool)$dados['concluido']);
        $agendamento->setMetodoPagamento($metodoPagamento);
        $agendamento->setHoraChegada(!empty($dados['horaChegada']) ? new \DateTime($dados['horaChegada']) : null);
        $agendamento->setHoraSaida(!empty($dados['horaSaida']) ? new \DateTime($dados['horaSaida']) : null);
        $agendamento->setTaxiDog((bool)$dados['taxi_dog']);
        $agendamento->setTaxaTaxiDog($dados['taxa_taxi_dog']);

        $repo->update($this->session->get('userId'), $agendamento);

        return $this->redirectToRoute('agendamento_index', ['data' => $agendamento->getData()->format('Y-m-d')]);
    }



    
    /**
     * @Route("/alterar-saida/{id}", name="agendamento_alterar_saida", methods={"POST"})
     */
    public function alterarHoraSaida(Request $request, int $id): Response
    {
        $repo = $this->getRepositorio(Agendamento::class);
        $dados = $repo->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (!$dados) {
            throw $this->createNotFoundException('O agendamento não foi encontrado.');
        }

        $horaSaida = $request->get('hora_saida');
        if ($horaSaida) {
            $agendamento = new Agendamento();
            $agendamento->setId($id);
            $agendamento->setData(new \DateTime($dados['data']));
            $agendamento->setPetId($dados['pet_id']);
            $agendamento->setServicoId($dados['servico_id']);
            $agendamento->setConcluido((bool)$dados['concluido']);
            $agendamento->setMetodoPagamento($dados['metodo_pagamento']);
            $agendamento->setHoraChegada($dados['horaChegada'] ? new \DateTime($dados['horaChegada']) : null);
            $agendamento->setHoraSaida(new \DateTime(date('Y-m-d') . ' ' . $horaSaida));
            $agendamento->setTaxiDog((bool)$dados['taxi_dog']);
            $agendamento->setTaxaTaxiDog($dados['taxa_taxi_dog']);

            $repo->update($this->session->get('userId'), $agendamento);
        }

        return $this->redirectToRoute('agendamento_index', ['data' => (new \DateTime($dados['data']))->format('Y-m-d')]);
    }

    /**
     * @Route("/agendamento/executar-acao/{id}", name="agendamento_executar_acao", methods={"POST"})
     */
    public function executarAcao(Request $request, int $id): Response
    {
        $repo = $this->getRepositorio(Agendamento::class);
        $dados = $repo->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (!$dados) {
            throw $this->createNotFoundException('O agendamento não foi encontrado.');
        }

        $acao = $request->get('acao');

        switch ($acao) {
            case 'editar':
                return $this->redirectToRoute('agendamento_editar', ['id' => $id]);

            case 'deletar':
                $repo->delete($this->session->get('userId'), $id);
                break;

            case 'concluir':
                if (!$dados['concluido']) {
                    $servico = $this->getRepositorio(Servico::class)->listaServicoPorId($this->session->get('userId'), $dados['servico_id']);
                    if (!$servico) {
                        throw $this->createNotFoundException('O serviço não foi encontrado.');
                    }

                    $financeiro = new Financeiro();
                    $financeiro->setDescricao('Serviço para o pet: ' . $dados['pet_id']);
                    $financeiro->setValor($servico['valor']);
                    $financeiro->setData(new \DateTime());
                    $financeiro->setPetId($dados['pet_id']);

                    $this->getRepositorio(Financeiro::class)->save($this->session->get('userId'), $financeiro);
                }

                $agendamento = new Agendamento();
                $agendamento->setId($id);
                $agendamento->setData(new \DateTime($dados['data']));
                $agendamento->setPetId($dados['pet_id']);
                $agendamento->setServicoId($dados['servico_id']);
                $agendamento->setConcluido(true);
                $agendamento->setMetodoPagamento($dados['metodo_pagamento']);
                $agendamento->setHoraChegada($dados['horaChegada'] ? new \DateTime($dados['horaChegada']) : null);
                $agendamento->setHoraSaida($dados['horaSaida'] ? new \DateTime($dados['horaSaida']) : null);
                $agendamento->setTaxiDog((bool)$dados['taxi_dog']);
                $agendamento->setTaxaTaxiDog($dados['taxa_taxi_dog']);

                $repo->update($this->session->get('userId'), $agendamento);
                break;

            case 'pendente':
                $servico = $this->getRepositorio(Servico::class)->listaServicoPorId($this->session->get('userId'), $dados['servico_id']);
                if (!$servico) {
                    throw $this->createNotFoundException('O serviço não foi encontrado.');
                }

                $valida = $this->getRepositorio(FinanceiroPendente::class)->verificaServicoExistente($this->session->get('userId'), $id);
                if (!$valida) {
                    $financeiroPendente = new FinanceiroPendente();
                    $financeiroPendente->setDescricao('Pagamento pendente - Serviço para o pet: ' . $dados['pet_id']);
                    $financeiroPendente->setValor($servico['valor']);
                    $financeiroPendente->setData(new \DateTime());
                    $financeiroPendente->setPetId($dados['pet_id']);
                    $financeiroPendente->setAgendamentoId($id);

                    $this->getRepositorio(FinanceiroPendente::class)->savePendente($this->session->get('userId'), $financeiroPendente);
                }

                $agendamento = new Agendamento();
                $agendamento->setId($id);
                $agendamento->setData(new \DateTime($dados['data']));
                $agendamento->setPetId($dados['pet_id']);
                $agendamento->setServicoId($dados['servico_id']);
                $agendamento->setConcluido((bool)$dados['concluido']);
                $agendamento->setMetodoPagamento('pendente');
                $agendamento->setHoraChegada($dados['horaChegada'] ? new \DateTime($dados['horaChegada']) : null);
                $agendamento->setHoraSaida($dados['horaSaida'] ? new \DateTime($dados['horaSaida']) : null);
                $agendamento->setTaxiDog((bool)$dados['taxi_dog']);
                $agendamento->setTaxaTaxiDog($dados['taxa_taxi_dog']);

                $repo->update($this->session->get('userId'), $agendamento);
                break;

            default:
                throw new \InvalidArgumentException('Ação inválida.');
        }

        return $this->redirectToRoute('agendamento_index', ['data' => (new \DateTime($dados['data']))->format('Y-m-d')]);
    }


}
