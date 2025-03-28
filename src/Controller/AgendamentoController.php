<?php
namespace App\Controller;

use App\Entity\Agendamento;
use App\Entity\AgendamentoPetServico;
use App\Entity\Cliente;
use App\Entity\Financeiro;
use App\Entity\FinanceiroPendente;
use App\Entity\Servico;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $data              = $request->query->get('data') ? new \DateTime($request->query->get('data')) : new \DateTime();
        $agendamentos      = $this->getRepositorio(Agendamento::class)->findByDate($this->session->get('userId'), $data);
        $totalAgendamentos = $this->getRepositorio(Agendamento::class)->contarAgendamentosPorData($this->session->get('userId'), $data);

        return $this->render('agendamento/index.html.twig', [
            'agendamentos'      => $agendamentos,
            'data'              => $data,
            'totalAgendamentos' => $totalAgendamentos,
        ]);
    }

/**
 * @Route("/novo", name="agendamento_novo", methods={"GET", "POST"})
 */
    public function novo(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $userId = $this->session->get('userId');

            $agendamento = new Agendamento();
            $agendamento->setData(new \DateTime($data['data']));
            $agendamento->setHoraChegada(new \DateTime($data['hora_chegada']));
            $agendamento->setMetodoPagamento($data['metodo_pagamento']);
            $agendamento->setTaxiDog($data['taxi_dog'] === 'sim');
            $agendamento->setTaxaTaxiDog($data['taxa_taxi_dog'] ?? 0);

            $em = $this->getRepositorio(Agendamento::class)->save($userId, $agendamento);

            // Processando pets e serviços associados
            foreach ($data['pets'] as $petData) {

                // Processar cada serviço vinculado ao pet
                foreach ($petData['servicos'] as $servicoId) {

                    // Criando a relação entre agendamento, pet e serviço
                    $agendamentoPetServico = new AgendamentoPetServico();
                    $agendamentoPetServico->setAgendamentoId($em);
                    $agendamentoPetServico->setPetId($petData['pet_id']);
                    $agendamentoPetServico->setServicoId($servicoId);

                    $this->getRepositorio(Agendamento::class)->saveAgendamentoServico($userId, $agendamentoPetServico);
                }
            }

            // Retorna para a index apenas após salvar todos os agendamentos
            return $this->redirectToRoute('agendamento_index', ['data' => $data['data']]);
        }

        $donos = $this->getRepositorio(Agendamento::class)->findAllDonos($this->session->get('userId'));

        return $this->render('agendamento/novo.html.twig', [
            'donos'    => $donos,
            'pets'     => $this->getRepositorio(Agendamento::class)->findAllPets($this->session->get('userId')),
            'servicos' => $this->getRepositorio(Agendamento::class)->findAllServicos($this->session->get('userId')),
        ]);
    }

    /**
     * @Route("/editar/{id}", name="agendamento_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $repo  = $this->getRepositorio(Agendamento::class);
        $dados = $this->getRepositorio(Agendamento::class)
            ->listaAgendamentoPorId($this->session->get('userId'), $request->get('id'));
        
        $aps = $this->getRepositorio(Agendamento::class)
            ->listaApsPorId($this->session->get('userId'), $request->get('id'));

        if (! $dados) {
            throw $this->createNotFoundException('O agendamento não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $userId = $this->session->get('userId');
            
            $agendamento = new Agendamento();
            $agendamento->setId($id);
            //$agendamento->setData(new \DateTime($request->get('data')));
            $agendamento->setConcluido((bool) $request->get('concluido'));
            $agendamento->setMetodoPagamento($dados['metodo_pagamento']);
            $agendamento->setTaxiDog($data['taxi_dog'] === 'sim');
            $agendamento->setTaxaTaxiDog($data['taxa_taxi_dog'] ?? 0);

            $this->getRepositorio(Agendamento::class)->updateAgendamento($this->session->get('userId'), $agendamento);

            return $this->redirectToRoute('agendamento_index', ['data' => (new \DateTime($request->get('data')))->format('Y-m-d')]);
        }

        return $this->render('agendamento/editar.html.twig', [
            'agendamentoId' => $request->get('id'),
            'agendamento' => $dados,
            'aps' => $repo->listaApsPorId($this->session->get('userId'), $request->get('id')),
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
        $repo  = $this->getRepositorio(Agendamento::class);
        $dados = $repo->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (! $dados) {
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
        $repo  = $this->getRepositorio(Agendamento::class);
        $dados = $repo->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (! $dados) {
            return $this->json(['status' => 'erro', 'mensagem' => 'Agendamento não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $metodoPagamento = $request->get('metodo_pagamento');

        $metodosPermitidos = [
            'dinheiro', 'pix', 'credito', 'debito', 'pendente',
            'pacote_semanal', 'pacote_semanal_1', 'pacote_semanal_2', 'pacote_semanal_3', 'pacote_semanal_4',
            'pacote_quinzenal',
        ];

        if (! in_array($metodoPagamento, $metodosPermitidos, true)) {
            return $this->json(['status' => 'erro', 'mensagem' => 'Método de pagamento inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $agendamento = new Agendamento();
        $agendamento->setId($id);
        $agendamento->setMetodoPagamento($metodoPagamento);

        $repo->updatePagamento($this->session->get('userId'), $agendamento);

        return $this->json(['status' => 'success', 'mensagem' => 'A forma de pagamento foi alterada com sucesso!']);
    }

    /**
     * @Route("/alterar-saida/{id}", name="agendamento_alterar_saida", methods={"POST"})
     */
    public function alterarHoraSaida(Request $request, int $id): Response
    {
        $repo  = $this->getRepositorio(Agendamento::class);
        $dados = $repo->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (! $dados) {
            return $this->json(['status' => 'erro', 'mensagem' => 'O agendamento não foi encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $horaSaida = $request->get('hora_saida');
        if ($horaSaida) {
            $agendamento = new Agendamento();
            $agendamento->setId($id);
            $agendamento->setHoraSaida((new \DateTime(date('Y-m-d') . ' ' . $horaSaida)));

            $repo->updateSaida($this->session->get('userId'), $agendamento);

            return $this->json(['status' => 'sucesso', 'mensagem' => 'Hora de saída atualizada.', 'hora_saida' => $horaSaida]);
        }

        return $this->json(['status' => 'erro', 'mensagem' => 'Hora de saída não informada.'], Response::HTTP_BAD_REQUEST);
    }

    /**
     * @Route("/agendamento/executar-acao/{id}/{acao}", name="agendamento_executar_acao")
     */
    public function executarAcao(Request $request, int $id, string $acao): Response
    {
        $repo  = $this->getRepositorio(Agendamento::class);
        $dados = $repo->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (! $dados) {
            return $this->json(['status' => 'erro', 'mensagem' => 'O agendamento não foi encontrado.'], Response::HTTP_NOT_FOUND);
        }


        switch ($acao) {
            case 'deletar':
                $repo->delete($this->session->get('userId'), $id);
                return $this->json(['status' => 'sucesso', 'mensagem' => 'Agendamento deletado com sucesso.', 'id' => $id]);

            case 'concluir':
                if (! $dados['concluido']) {

                    $listaServicoPorAgendamento = $this->getRepositorio(Agendamento::class)->listaApsPorId($this->session->get('userId'), $id);

                    foreach ($listaServicoPorAgendamento as $row) {
                        // Registrar no financeiro quando o serviço for concluído
                        $financeiro = new Financeiro();
                        $financeiro->setDescricao('Serviço para o pet: ' . $row['pet_nome']);
                        $financeiro->setValor($row['valor']);
                        $financeiro->setData(new \DateTime());
                        $financeiro->setPetId($row['petId']);

                        if ($dados['taxi_dog']) {
                            $financeiro->setValor($financeiro->getValor() + $dados['taxa_taxi_dog']);
                            $financeiro->setDescricao($financeiro->getDescricao() . ' + Táxi Dog');
                        }

                        $this->getRepositorio(Financeiro::class)->save($this->session->get('userId'), $financeiro);
                    }
                }

                $repo->updateConcluido($this->session->get('userId'), $id);

                return $this->json([
                    'status'    => 'success',
                    'mensagem'  => 'Agendamento concluído com sucesso.',
                    'id'        => $id,
                    'concluido' => true,
                ]);

            case 'pendente':
                // Verificar se já existe um lançamento no financeiro pendente
                $financeiroRepo = $this->getRepositorio(FinanceiroPendente::class);
                $servico        = $this->getRepositorio(Servico::class)->listaServicoPorId($this->session->get('userId'), $dados['servico_id']);

                if (! $servico) {
                    return $this->json(['status' => 'erro', 'mensagem' => 'O serviço não foi encontrado.'], Response::HTTP_NOT_FOUND);
                }

                // Verifica se já existe um lançamento pendente para evitar duplicação
                $existePendente = $financeiroRepo->verificaServicoExistente($this->session->get('userId'), $id);
                if (! $existePendente) {
                    $financeiroPendente = new FinanceiroPendente();
                    $financeiroPendente->setDescricao('Pagamento pendente - Serviço para o pet: ' . $dados['pet_id']);
                    $financeiroPendente->setValor($servico['valor']);
                    $financeiroPendente->setData(new \DateTime());
                    $financeiroPendente->setPetId($dados['pet_id']);
                    $financeiroPendente->setAgendamentoId($id);

                    $financeiroRepo->savePendente($this->session->get('userId'), $financeiroPendente);
                }

                // Atualiza o agendamento para "pendente"
                $agendamento = new Agendamento();
                $agendamento->setId($id);
                $agendamento->setData(new \DateTime($dados['data']));
                $agendamento->setPetId($dados['pet_id']);
                $agendamento->setServicoId($dados['servico_id']);
                $agendamento->setConcluido(false);
                $agendamento->setMetodoPagamento('pendente');
                $agendamento->setHoraChegada($dados['horaChegada'] ? new \DateTime($dados['horaChegada']) : null);
                $agendamento->setHoraSaida($dados['horaSaida'] ? new \DateTime($dados['horaSaida']) : null);
                $agendamento->setTaxiDog((bool) $dados['taxi_dog']);
                $agendamento->setTaxaTaxiDog($dados['taxa_taxi_dog']);

                $repo->update($this->session->get('userId'), $agendamento);

                return $this->json([
                    'status'           => 'sucesso',
                    'mensagem'         => 'Agendamento marcado como pendente e registrado no financeiro pendente.',
                    'id'               => $id,
                    'metodo_pagamento' => 'pendente',
                ]);

            default:
                return $this->json(['status' => 'erro', 'mensagem' => 'Ação inválida.'], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @Route("/api/buscar-cliente", name="api_buscar_cliente", methods="POST")
     */
    public function buscarCliente(Request $request): JsonResponse
    {
        $petId = $request->get('pet_id');

        if (! $petId) {
            return $this->json(['status' => 'erro', 'mensagem' => 'Pet ID não informado.'], Response::HTTP_BAD_REQUEST);
        }

        $baseId = $this->session->get('userId');

        $cliente = $this->getRepositorio(Cliente::class)->findAgendamentosByCliente($baseId, $petId);

        if (! $cliente) {
            return $this->json(['status' => 'erro', 'mensagem' => 'Cliente não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['status' => 'sucesso', 'cliente' => $cliente]);
    }

}
