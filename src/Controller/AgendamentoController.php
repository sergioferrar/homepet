<?php

namespace App\Controller;

use App\Entity\Agendamento;
use App\Entity\Financeiro;
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
    private $agendamentoRepository;
    private $financeiroRepository;
    private $servicoRepository;

    public function __construct(AgendamentoRepository $agendamentoRepository, FinanceiroRepository $financeiroRepository, ServicoRepository $servicoRepository)
    {
        $this->agendamentoRepository = $agendamentoRepository;
        $this->financeiroRepository = $financeiroRepository;
        $this->servicoRepository = $servicoRepository;
    }

    /**
     * @Route("/", name="agendamento_index", methods={"GET"})
     */
    public function index(Request $request): Response
    {
        $data = $request->query->get('data') ? new \DateTime($request->query->get('data')) : new \DateTime();
        $agendamentos = $this->agendamentoRepository->findByDate($data);
        $totalAgendamentos = $this->agendamentoRepository->contarAgendamentosPorData($data);

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
            $data = $request->request->get('data');
            $petId = $request->request->get('pet_id');
            $servicoId = $request->request->get('servico_id');
            $recorrencia = $request->request->get('recorrencia');
            $metodoPagamento = $request->request->get('metodo_pagamento', 'pendente');
            $horaChegada = $request->request->get('hora_chegada');
            $taxiDog = $request->request->get('taxi_dog') ? true : false;
            $taxaTaxiDog = $request->request->get('taxa_taxi_dog') ?: null; // Se não enviado, mantém NULL

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

            $this->agendamentoRepository->save($agendamento);

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

                    $this->agendamentoRepository->save($agendamentoRecorrente);
                    $data = $novaData->format('Y-m-d');
                }
            }

            return $this->redirectToRoute('agendamento_index', ['data' => (new \DateTime($data))->format('Y-m-d')]);
        }

        return $this->render('agendamento/novo.html.twig', [
            'pets' => $this->agendamentoRepository->findAllPets(),
            'servicos' => $this->agendamentoRepository->findAllServicos(),
        ]);
    }



    /**
     * @Route("/editar/{id}", name="agendamento_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $agendamento = $this->agendamentoRepository->find($id);

        if (!$agendamento) {
            throw $this->createNotFoundException('O agendamento não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->get('data');
            $petId = $request->request->get('pet_id');
            $servicoId = $request->request->get('servico_id');
            $concluido = $request->request->get('concluido') ? true : false;
//dd();
            $agendamento->setData((new \DateTime($data)));
            $agendamento->setPet_Id($petId);
            $agendamento->setServico_Id($servicoId);
            $agendamento->setConcluido($concluido);

            $this->agendamentoRepository->update($agendamento);
            return $this->redirectToRoute('agendamento_index', ['data' => (new \DateTime($data))->format('Y-m-d')]);
        }

        return $this->render('agendamento/editar.html.twig', [
            'agendamento' => $agendamento,
            'pets' => $this->agendamentoRepository->findAllPets(),
            'servicos' => $this->agendamentoRepository->findAllServicos(),
        ]);
    }

    /**
     * @Route("/deletar/{id}", name="agendamento_deletar", methods={"POST"})
     */
    public function deletar(Request $request, int $id): Response
    {
        $agendamento = $this->agendamentoRepository->find($id);

        if (!$agendamento) {
            throw $this->createNotFoundException('O agendamento não foi encontrado');
        }

        $this->agendamentoRepository->delete($id);
        return $this->redirectToRoute('agendamento_index');
    }

    /**
     * @Route("/concluir/{id}", name="agendamento_concluir", methods={"POST"})
     */
    public function concluir(int $id): Response
    {
        $agendamento = $this->agendamentoRepository->find($id);

        if (!$agendamento) {
            throw $this->createNotFoundException('O agendamento não foi encontrado');
        }

        $servico = $this->servicoRepository->find($agendamento->getServico_Id());

        if (!$servico) {
            throw $this->createNotFoundException('O serviço não foi encontrado');
        }

        // Criar registro financeiro
        $financeiro = new Financeiro();
        $financeiro->setDescricao('Serviço para o pet: ' . $agendamento->getPet_Id());
        $financeiro->setValor($servico->getValor());
        $financeiro->setData(new \DateTime());
        $financeiro->setPetId($agendamento->getPet_Id());

        // Adicionar taxa do táxi dog, se aplicável
        if ($agendamento->getTaxiDog() && $agendamento->getTaxaTaxiDog()) {
            $financeiro->setValor($financeiro->getValor() + $agendamento->getTaxaTaxiDog());
            $financeiro->setDescricao($financeiro->getDescricao() . ' + Táxi Dog');
        }

        $this->financeiroRepository->save($financeiro);

        $agendamento->setConcluido(true);
        $this->agendamentoRepository->update($agendamento);

        return $this->redirectToRoute('agendamento_index');
    }

    /**
     * @Route("/agendamento/pronto/{id}", name="agendamento_pronto")
     */
    public function marcarComoPronto($id)
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
        $agendamento = $this->agendamentoRepository->find($id);
        if (!$agendamento) {
            throw $this->createNotFoundException('Agendamento não encontrado.');
        }

        $metodoPagamento = $request->request->get('metodo_pagamento');
        if (!in_array($metodoPagamento, ['dinheiro', 'pix', 'credito', 'debito', 'pendente'])) {
            throw new \InvalidArgumentException('Método de pagamento inválido.');
        }

        $agendamento->setMetodoPagamento($metodoPagamento);
        $this->agendamentoRepository->update($agendamento);

        return $this->redirectToRoute('agendamento_index', ['data' => $agendamento->getData()->format('Y-m-d')]);
    }

    /**
     * @Route("/alterar-saida/{id}", name="agendamento_alterar_saida", methods={"POST"})
     */
    public function alterarHoraSaida(Request $request, int $id): Response
    {
        $agendamento = $this->agendamentoRepository->find($id);
        if (!$agendamento) {
            throw $this->createNotFoundException('O agendamento não foi encontrado.');
        }

        $horaSaida = $request->request->get('hora_saida');
        if ($horaSaida) {
            $agendamento->setHoraSaida(new \DateTime(date('Y-m-d') . ' ' . $horaSaida));
            $this->agendamentoRepository->update($agendamento);
        }

        return $this->redirectToRoute('agendamento_index');
    }

    /**
     * @Route("/agendamento/executar-acao/{id}", name="agendamento_executar_acao", methods={"POST"})
     */
    public function executarAcao(Request $request, int $id): Response
    {
        $agendamento = $this->agendamentoRepository->find($id);

        if (!$agendamento) {
            throw $this->createNotFoundException('O agendamento não foi encontrado.');
        }

        $acao = $request->request->get('acao');

        switch ($acao) {
            case 'editar':
                return $this->redirectToRoute('agendamento_editar', ['id' => $id]);

            case 'deletar':
                $this->agendamentoRepository->delete($id);
                return $this->redirectToRoute('agendamento_index');

            case 'concluir':
                if (!$agendamento->getConcluido()) {
                    $servico = $this->servicoRepository->find($agendamento->getServico_Id());
                    if (!$servico) {
                        throw $this->createNotFoundException('O serviço não foi encontrado.');
                    }

                    $financeiro = new Financeiro();
                    $financeiro->setDescricao('Serviço para o pet: ' . $agendamento->getPet_Id());
                    $financeiro->setValor($servico->getValor());
                    $financeiro->setData(new \DateTime());
                    $financeiro->setPetId($agendamento->getPet_Id());

                    $this->financeiroRepository->save($financeiro);
                }

                $agendamento->setConcluido(true);
                $this->agendamentoRepository->update($agendamento);
                return $this->redirectToRoute('agendamento_index');

            case 'pendente':
                $servico = $this->servicoRepository->find($agendamento->getServico_Id());
                if (!$servico) {
                    throw $this->createNotFoundException('O serviço não foi encontrado.');
                }

                // Criar entrada no financeiro pendente
                $financeiroPendente = new Financeiro();
                $financeiroPendente->setDescricao('Pagamento pendente - Serviço para o pet: ' . $agendamento->getPet_Id());
                $financeiroPendente->setValor($servico->getValor());
                $financeiroPendente->setData(new \DateTime());
                $financeiroPendente->setPetId($agendamento->getPet_Id());

                // Salvando no Financeiro Pendente (Novo método savePendente no repositório)
                $this->financeiroRepository->savePendente($financeiroPendente);

                $agendamento->setMetodoPagamento('pendente');
                $this->agendamentoRepository->update($agendamento);
                return $this->redirectToRoute('agendamento_index');
        }

        return $this->redirectToRoute('agendamento_index');
    }




}
