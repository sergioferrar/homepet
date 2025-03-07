<?php

namespace App\Controller;

use App\Entity\Agendamento;
use App\Entity\Financeiro;
use App\Entity\FinanceiroPendente;
use App\Entity\Servico;
use App\Repository\AgendamentoRepository;
use App\Repository\FinanceiroRepository;
use App\Repository\ServicoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
            $data = $request->get('data');
            $petId = $request->get('pet_id');
            $servicoId = $request->get('servico_id');
            $recorrencia = $request->get('recorrencia');
            $metodoPagamento = $request->get('metodo_pagamento', 'pendente');
            $horaChegada = $request->get('hora_chegada');
            $taxiDog = $request->get('taxi_dog') ? true : false;
            $taxaTaxiDog = $request->get('taxa_taxi_dog') ?: null; // Se não enviado, mantém NULL

            $agendamento = new Agendamento();
            $agendamento->setData(new \DateTime($data));
            $agendamento->setPet_Id($petId);
            $agendamento->setServico_Id($servicoId);
            $agendamento->setConcluido(false);
            $agendamento->setMetodoPagamento($metodoPagamento);
            $agendamento->setTaxiDog($taxiDog);
            $agendamento->setTaxaTaxiDog($taxaTaxiDog);

            if ($horaChegada) {
                $agendamento->setHoraChegada(new \DateTime($data . ' ' . $horaChegada));
            }

            $this->getRepositorio(Agendamento::class)->save($this->session->get('userId'), $agendamento);

            // Tratamento de recorrência
            if ($recorrencia === 'semanal' || $recorrencia === 'quinzenal') {
                $intervalo = $recorrencia === 'semanal' ? 'P1W' : 'P2W';

                for ($i = 1; $i < 4; $i++) {
                    $novaData = (new \DateTime($data))->add(new \DateInterval($intervalo));
                    $agendamentoRecorrente = new Agendamento();
                    $agendamentoRecorrente->setData($novaData);
                    $agendamentoRecorrente->setPet_Id($petId);
                    $agendamentoRecorrente->setServico_Id($servicoId);
                    $agendamentoRecorrente->setConcluido(false);
                    $agendamentoRecorrente->setMetodoPagamento($metodoPagamento);
                    $agendamentoRecorrente->setTaxiDog($taxiDog);
                    $agendamentoRecorrente->setTaxaTaxiDog($taxaTaxiDog);

                    if ($horaChegada) {
                        $agendamentoRecorrente->setHoraChegada(new \DateTime($novaData->format('Y-m-d') . ' ' . $horaChegada));
                    }

                    $this->getRepositorio(Agendamento::class)->save($this->session->get('userId'), $agendamentoRecorrente);
                    $data = $novaData->format('Y-m-d');
                }
            }

            return $this->redirectToRoute('agendamento_index', ['data' => (new \DateTime($data))->format('Y-m-d')]);
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
        $agendamento = $this->getRepositorio(Agendamento::class)->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (!$agendamento) {
            throw $this->createNotFoundException('O agendamento não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $data = $request->get('data');
            $petId = $request->get('pet_id');
            $servicoId = $request->get('servico_id');
            $concluido = $request->get('concluido') ? true : false;

//            $agendamentos = new Agendamento();
            $agendamento['data'] = (new \DateTime($data));
            $agendamento['pet_id'] = $petId;
            $agendamento['servico_id'] = $servicoId;
            $agendamento['concluido'] = $concluido;

            $this->getRepositorio(Agendamento::class)->update($this->session->get('userId'), $agendamento);
            return $this->redirectToRoute('agendamento_index', ['data' => (new \DateTime($data))->format('Y-m-d')]);
        }

        return $this->render('agendamento/editar.html.twig', [
            'agendamento' => $agendamento,
            'pets' => $this->getRepositorio(Agendamento::class)->findAllPets($this->session->get('userId')),
            'servicos' => $this->getRepositorio(Agendamento::class)->findAllServicos($this->session->get('userId')),
        ]);
    }

    /**
     * @Route("/deletar/{id}", name="agendamento_deletar", methods={"POST"})
     */
    public function deletar(Request $request, int $id): Response
    {
        $agendamento = $this->getRepositorio(Agendamento::class)->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (!$agendamento) {
            throw $this->createNotFoundException('O agendamento não foi encontrado');
        }

        $this->getRepositorio(Agendamento::class)->delete($this->session->get('userId'), $id);
        return $this->redirectToRoute('agendamento_index');
    }

    /**
     * @Route("/concluir/{id}", name="agendamento_concluir", methods={"POST"})
     */
    public function concluir(Request $request, int $id): Response
    {
        $agendamento = $this->getRepositorio(Agendamento::class)->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (!$agendamento) {
            throw $this->createNotFoundException('O agendamento não foi encontrado');
        }

        $servico = $this->getRepositorio(Servico::class)->listaServicoPorId($this->session->get('userId'), $agendamento['servico_id']);

        if (!$servico) {
            throw $this->createNotFoundException('O serviço não foi encontrado');
        }

        // Criar registro financeiro
        $financeiro = new Financeiro();
        $financeiro->setDescricao('Serviço para o pet: ' . $agendamento['pet_id']);
        $financeiro->setValor($servico['valor']);
        $financeiro->setData(new \DateTime());
        $financeiro->setPetId($agendamento['pet_id']);

        // Adicionar taxa do táxi dog, se aplicável
        if ($agendamento['taxi_dog'] && $agendamento['taxa_taxi_dog']) {
            $financeiro->setValor($financeiro->getValor() + $agendamento['taxa_taxi_dog']);
            $financeiro->setDescricao($financeiro->getDescricao() . ' + Táxi Dog');
        }

        $this->getRepositorio(Financeiro::class)->save($this->session->get('userId'), $financeiro);

        $agendamento['concluido'] = true;
        $this->getRepositorio(Agendamento::class)->update($agendamento);

        return $this->redirectToRoute('agendamento_index');
    }

    /**
     * @Route("/agendamento/pronto/{id}", name="agendamento_pronto")
     */
    public function marcarComoPronto(Request $request, $id)
    {
        $agendamento = $this->find($id);
        if ($agendamento) {
            $agendamento->setConcluido(true);
            $this->_em->persist($agendamento);
            $this->_em->flush();
        }
    }


    /**
     * @Route("/agendamentos", name="lista_agendamentos")
     */
    public function listar(AgendamentoRepository $agendamentoRepository): Response
    {
        $agendamentos = $agendamentoRepository->findAll();
        return $this->render('agendamento/lista.html.twig', ['agendamentos' => $agendamentos]);
    }

    /**
     * @Route("/alterar-pagamento/{id}", name="agendamento_alterar_pagamento", methods={"POST"})
     */
    public function alterarPagamento(int $id, Request $request): Response
    {
        $agendamento = $this->getRepositorio(Agendamento::class)->listaAgendamentoPorId($this->session->get('userId'), $id);
        if (!$agendamento) {
            throw $this->createNotFoundException('Agendamento não encontrado.');
        }

        $metodoPagamento = $request->get('metodo_pagamento');
        if (!in_array($metodoPagamento, ['dinheiro', 'pix', 'credito', 'debito', 'pendente'])) {
            throw new \InvalidArgumentException('Método de pagamento inválido.');
        }

        $agendamento['metodo_pagamento'] = $metodoPagamento;
        $this->getRepositorio(Agendamento::class)->update($this->session->get('userId'), $agendamento);

        return $this->redirectToRoute('agendamento_index', ['data' => date('Y-m-d', strtotime($agendamento['data']))]);
    }

    /**
     * @Route("/alterar-saida/{id}", name="agendamento_alterar_saida", methods={"POST"})
     */
    public function alterarHoraSaida(Request $request, int $id): Response
    {
        $agendamento = $this->getRepositorio(Agendamento::class)->listaAgendamentoPorId($this->session->get('userId'), $id);
        if (!$agendamento) {
            throw $this->createNotFoundException('O agendamento não foi encontrado.');
        }

        $horaSaida = $request->get('hora_saida');
        if ($horaSaida) {
            $agendamento['horaSaida'] = (new \DateTime(date('Y-m-d') . ' ' . $horaSaida))->format('Y-m-d H:i:s');
            $this->getRepositorio(Agendamento::class)->update($this->session->get('userId'), $agendamento);
        }

        return $this->redirectToRoute('agendamento_index');
    }

    /**
     * @Route("/agendamento/executar-acao/{id}", name="agendamento_executar_acao", methods={"POST"})
     */
    public function executarAcao(Request $request, int $id): Response
    {
        $agendamento = $this->getRepositorio(Agendamento::class)->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (!$agendamento) {
            throw $this->createNotFoundException('O agendamento não foi encontrado.');
        }

        $acao = $request->get('acao');

        switch ($acao) {
            case 'editar':
                return $this->redirectToRoute('agendamento_editar', ['id' => $id]);

            case 'deletar':
                $this->getRepositorio(Agendamento::class)->delete($this->session->get('userId'), $id);
                return $this->redirectToRoute('agendamento_index');

            case 'concluir':
                if (!$agendamento['concluido']) {
                    $servico = $this->getRepositorio(Servico::class)->listaServicoPorId($this->session->get('userId'), $agendamento['servico_id']);
                    if (!$servico) {
                        throw $this->createNotFoundException('O serviço não foi encontrado.');
                    }

                    $financeiro = new Financeiro();
                    $financeiro->setDescricao('Serviço para o pet: ' . $agendamento['pet_id']);
                    $financeiro->setValor($servico['valor']);//
                    $financeiro->setData(new \DateTime());
                    $financeiro->setPetId($agendamento['pet_id']);

                    $this->getRepositorio(Financeiro::class)->save($this->session->get('userId'), $financeiro);
                }

                $agendamento['concluido'] = true;//->setConcluido(true);
                $this->getRepositorio(Agendamento::class)->update($this->session->get('userId'), $agendamento);
                return $this->redirectToRoute('agendamento_index');

            case 'pendente':
                $servico = $this->getRepositorio(Servico::class)->listaServicoPorId($this->session->get('userId'), $agendamento['servico_id']);
                if (!$servico) {
                    throw $this->createNotFoundException('O serviço não foi encontrado.');
                }

                $valida = $this->getRepositorio(FinanceiroPendente::class)->verificaServicoExistente($this->session->get('userId'), $request->get('id'));
//dd($valida, $request->get('id'));
                if($valida){
                    return $this->redirectToRoute('agendamento_index');
                }

                // Criar entrada no financeiro pendente
                $financeiroPendente = new FinanceiroPendente();
                $financeiroPendente->setDescricao('Pagamento pendente - Serviço para o pet: ' . $agendamento['pet_id']);
                $financeiroPendente->setValor($servico['valor']);
                $financeiroPendente->setData(new \DateTime());
                $financeiroPendente->setPetId($agendamento['pet_id']);
                $financeiroPendente->setAgendamentoId($request->get('id'));

                // Salvando no Financeiro Pendente (Novo método savePendente no repositório)
                $this->getRepositorio(FinanceiroPendente::class)->savePendente($this->session->get('userId'), $financeiroPendente);

                $agendamento['metodo_pagamento'] = 'pendente';
                $this->getRepositorio(Agendamento::class)->update($this->session->get('userId'), $agendamento);
                return $this->redirectToRoute('agendamento_index');
        }

        return $this->redirectToRoute('agendamento_index');
    }

}
