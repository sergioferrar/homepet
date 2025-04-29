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
        $this->switchDB();
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
        $this->switchDB();
        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $userId = $this->session->get('userId');

            $agendamento = new Agendamento();
            $agendamento->setData(new \DateTime($data['data']));
            $agendamento->setHoraChegada(new \DateTime($data['hora_chegada']));
            $agendamento->setMetodoPagamento($data['metodo_pagamento']);
            $agendamento->setTaxiDog($data['taxi_dog'] === 'sim');
            $agendamento->setStatus('aguardando');
            $agendamento->setTaxaTaxiDog(
                isset($data['taxa_taxi_dog']) && $data['taxa_taxi_dog'] !== ''
                    ? (float) $data['taxa_taxi_dog']
                    : null
            );
            $agendamento->setStatus('aguardando');
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
        $this->switchDB();
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
            $agendamento->setConcluido((bool) $dados['concluido']);
            $agendamento->setMetodoPagamento($data['metodo_pagamento']);
            $agendamento->setData(new \DateTime($data['data']));
            $agendamento->setHoraChegada(new \DateTime($data['hora_chegada']));
            $agendamento->setTaxiDog(isset($data['taxi_dog']) && $data['taxi_dog'] === 'sim');
            $agendamento->setTaxaTaxiDog(
                isset($data['taxa_taxi_dog']) && $data['taxa_taxi_dog'] !== ''
                    ? (float) $data['taxa_taxi_dog']
                    : null
            );

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
        $this->switchDB();
        $this->getRepositorio(Agendamento::class)->delete($this->session->get('userId'), $id);
        return $this->redirectToRoute('agendamento_index');
    }

    /**
     * @Route("/concluir/{id}", name="agendamento_concluir", methods={"GET"})
     */
    public function concluir(int $id): JsonResponse
    {
        $this->switchDB();
        $repo  = $this->getRepositorio(Agendamento::class);
        $dados = $repo->listaAgendamentoPorId($this->session->get('userId'), $id);

        if (!$dados) {
            return $this->json(['status' => 'erro', 'mensagem' => 'O agendamento não foi encontrado.'], Response::HTTP_NOT_FOUND);
        }

        // Buscar os serviços associados ao agendamento
        $listaServicoPorAgendamento = $repo->listaApsPorId($this->session->get('userId'), $id);

        // Agrupar serviços por cliente
        $clientes = [];
        $totalGeral = 0;

        foreach ($listaServicoPorAgendamento as $row) {
            $clienteId = $row['cliente_nome'];

            if (!isset($clientes[$clienteId])) {
                $clientes[$clienteId] = [
                    'servicos' => [],
                    'valor_total' => 0,
                    'petIds' => [],
                ];
            }

            $clientes[$clienteId]['servicos'][] = [
                'servico_nome' => $row['servico_nome'],
                'pet_nome' => $row['pet_nome'],
                'valor' => (float) $row['valor'],
            ];
            $clientes[$clienteId]['valor_total'] += (float) $row['valor'];
            $clientes[$clienteId]['petIds'][] = $row['petId'];

            $totalGeral += (float) $row['valor'];
        }

        // Adicionar Táxi Dog ao total, se aplicável
        if ($dados['taxi_dog']) {
            $totalGeral += (float) $dados['taxa_taxi_dog'];
            foreach ($clientes as &$cliente) {
                $cliente['valor_total'] += (float) $dados['taxa_taxi_dog'];
            }
        }

        // Buscar registros pendentes associados ao agendamento
        $pendentes = [];
        try {
            $financeiroPendenteRepo = $this->getRepositorio(FinanceiroPendente::class);
            $pendentes = $financeiroPendenteRepo->findByBaseId($this->session->get('userId'), ['agendamentoId' => $id]);
        } catch (\Exception $e) {
            error_log("Erro ao buscar registros pendentes: " . $e->getMessage());
        }

        return $this->json([
            'status' => 'success',
            'agendamento' => $dados,
            'clientes' => $clientes,
            'total_geral' => $totalGeral,
            'pendentes' => $pendentes,
        ]);
    }

    /**
     * @Route("/alterar-pagamento/{id}", name="agendamento_alterar_pagamento", methods={"POST"})
     */
    public function alterarPagamento(int $id, Request $request): Response
    {
        $this->switchDB();
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
        $this->switchDB();
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
        $this->switchDB();
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
                if (!$dados['concluido']) {
                    $listaServicoPorAgendamento = $this->getRepositorio(Agendamento::class)
                        ->listaApsPorId($this->session->get('userId'), $id);

                    // Agrupar serviços por cliente, não por pet
                    $clientes = [];

                    foreach ($listaServicoPorAgendamento as $row) {
                        $clienteId = $row['cliente_nome']; // pode usar ID do cliente se tiver disponível

                        if (!isset($clientes[$clienteId])) {
                            $clientes[$clienteId] = [
                                'descricao' => [],
                                'valor_total' => 0,
                                'petIds' => [],
                            ];
                        }

                        $clientes[$clienteId]['descricao'][] = "{$row['servico_nome']} para {$row['pet_nome']}";
                        $clientes[$clienteId]['valor_total'] += (float) $row['valor'];
                        $clientes[$clienteId]['petIds'][] = $row['petId'];
                    }

                    // Agora, cria APENAS UM REGISTRO NO FINANCEIRO POR CLIENTE
                    foreach ($clientes as $clienteNome => $info) {
                        $descricaoFinal = implode(', ', $info['descricao']);

                        // Táxi Dog, apenas uma vez por cliente
                        if ($dados['taxi_dog']) {
                            $info['valor_total'] += (float) $dados['taxa_taxi_dog'];
                            $descricaoFinal .= ' + Táxi Dog';
                        }

                        $financeiro = new Financeiro();
                        $financeiro->setDescricao($descricaoFinal);
                        $financeiro->setValor($info['valor_total']);
                        $financeiro->setData(new \DateTime());
                        $financeiro->setPetId($info['petIds'][0]); // Aqui você pode deixar o primeiro pet ou nulo.

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
                $financeiroRepo = $this->getRepositorio(FinanceiroPendente::class);
                $listaServicoPorAgendamento = $this->getRepositorio(Agendamento::class)->listaApsPorId($this->session->get('userId'), $id);

                // Agrupar serviços por cliente, similar ao 'concluir'
                $clientes = [];

                foreach ($listaServicoPorAgendamento as $row) {
                    $clienteId = $row['cliente_nome'];

                    if (!isset($clientes[$clienteId])) {
                        $clientes[$clienteId] = [
                            'descricao'   => [],
                            'valor_total' => 0,
                            'petIds'      => [],
                        ];
                    }

                    $clientes[$clienteId]['descricao'][] = "{$row['servico_nome']} para {$row['pet_nome']}";
                    $clientes[$clienteId]['valor_total'] += (float) $row['valor'];
                    $clientes[$clienteId]['petIds'][] = $row['petId'];
                }

                // Criar um registro no financeiro pendente por cliente
                foreach ($clientes as $clienteNome => $info) {
                    $descricaoFinal = "Pagamento pendente - " . implode(', ', $info['descricao']);

                    if ($dados['taxi_dog']) {
                        $info['valor_total'] += (float) $dados['taxa_taxi_dog'];
                        $descricaoFinal .= ' + Táxi Dog';
                    }

                    // Verificar se já existe um registro pendente para este agendamento e cliente
                    $existePendente = $financeiroRepo->verificaServicoExistente($this->session->get('userId'), $id, $info['petIds'][0]);

                    if (!$existePendente) {
                        $financeiroPendente = new FinanceiroPendente();
                        $financeiroPendente->setDescricao($descricaoFinal);
                        $financeiroPendente->setValor($info['valor_total']);
                        $financeiroPendente->setData(new \DateTime());
                        $financeiroPendente->setPetId($info['petIds'][0]); // Usa o primeiro pet do cliente
                        $financeiroPendente->setAgendamentoId($id);

                        try {
                            $financeiroRepo->savePendente($this->session->get('userId'), $financeiroPendente);
                        } catch (\Exception $e) {
                            error_log("Erro ao salvar no financeiro pendente: " . $e->getMessage());
                            return $this->json([
                                'status'   => 'erro',
                                'mensagem' => 'Erro ao registrar no financeiro pendente: ' . $e->getMessage(),
                            ], Response::HTTP_INTERNAL_SERVER_ERROR);
                        }
                    }
                }

                // Atualiza o agendamento para "pendente"
                $agendamento = new Agendamento();
                $agendamento->setId($id);
                $agendamento->setData(new \DateTime($dados['data']));
                $agendamento->setConcluido(false);
                $agendamento->setMetodoPagamento('pendente');
                $agendamento->setHoraChegada($dados['horaChegada'] ? new \DateTime($dados['horaChegada']) : null);
                $agendamento->setHoraSaida($dados['horaSaida'] ? new \DateTime($dados['horaSaida']) : null);
                $agendamento->setTaxiDog((bool) $dados['taxi_dog']);
                $agendamento->setTaxaTaxiDog(
                    isset($dados['taxa_taxi_dog']) && $dados['taxa_taxi_dog'] !== ''
                        ? (float) $dados['taxa_taxi_dog']
                        : null
                );

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
        $this->switchDB();
        $donoId = $request->get('dono_id'); // <-- Adiciona isso

        if (!$donoId) {
            return $this->json(['status' => 'erro', 'mensagem' => 'Dono ID não informado.'], Response::HTTP_BAD_REQUEST);
        }

        $baseId = $this->session->get('userId');

        $cliente = $this->getRepositorio(\App\Entity\Cliente::class)->localizaTodosClientePorID($baseId, $donoId);

        if (! $cliente) {
            return $this->json(['status' => 'erro', 'mensagem' => 'Cliente não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['status' => 'sucesso', 'cliente' => $cliente]);
    }



    /**
     * @Route("/agendamento/quadro", name="agendamento_quadro")
     */
    public function quadroDeTarefas(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');
        $data = $request->query->get('data') ? new \DateTime($request->query->get('data')) : new \DateTime();

        $itens = $this->getRepositorio(Agendamento::class)->listarQuadroPorPet($baseId, $data);

        $aguardando = [];
        $em_processo = [];
        $finalizado = [];

        $processed = [];

        foreach ($itens as $item) {
            $key = $item['agendamento_id'] . '-' . $item['pet_id'];

            if (isset($processed[$key])) {
                continue;
            }

            $processed[$key] = true;

            switch ($item['status']) {
                case 'em_processo':
                    $em_processo[] = $item;
                    break;
                case 'finalizado':
                    $finalizado[] = $item;
                    break;
                default:
                    $aguardando[] = $item;
            }
        }

        return $this->render('agendamento/quadro.html.twig', [
            'aguardando' => $aguardando,
            'em_processo' => $em_processo,
            'finalizado' => $finalizado,
            'data' => $data, // Passa a data para o template
        ]);
    }

    /**
     * @Route("/alterar-status-pet/{id}", name="alterar_status_pet", methods={"POST"})
     */
    public function alterarStatusPet(Request $request, int $id): JsonResponse
    {
        $this->switchDB();
        $status = json_decode($request->getContent(), true)['status'] ?? null;

        if (!in_array($status, ['aguardando', 'em_processo', 'finalizado'], true)) {
            return $this->json(['status' => 'erro', 'mensagem' => 'Status inválido.'], 400);
        }

        $this->getRepositorio(Agendamento::class)->atualizarStatusPetServico(
            $this->session->get('userId'),
            $id,
            $status
        );

        return $this->json(['status' => 'sucesso', 'mensagem' => 'Status atualizado com sucesso.']);
    }

     /**
     * @Route("/concluir-pagamento/{id}", name="agendamento_concluir_pagamento", methods={"POST"})
     */
    public function concluirPagamento(Request $request, int $id): JsonResponse
    {
        $this->switchDB();
        $repo = $this->getRepositorio(Agendamento::class);
        $dados = $repo->listaAgendamentoPorId($this->session->get('userId'), $id);


        if (!$dados) {
            return $this->json(['status' => 'erro', 'mensagem' => 'O agendamento não foi encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if ($dados['concluido']) {
            return $this->json(['status' => 'erro', 'mensagem' => 'Este agendamento já foi concluído anteriormente.'], Response::HTTP_BAD_REQUEST);
        }

        $desconto = (float) $request->request->get('desconto', 0);
        $metodoPagamento = $request->request->get('metodo_pagamento');

        $listaServicoPorAgendamento = $repo->listaApsPorId($this->session->get('userId'), $id);
        $clientes = [];
        $totalGeral = 0;

        foreach ($listaServicoPorAgendamento as $row) {
            $clienteId = $row['cliente_nome'];

            if (!isset($clientes[$clienteId])) {
                $clientes[$clienteId] = [
                    'descricao' => [],
                    'valor_total' => 0,
                    'petIds' => [],
                ];
            }

            $clientes[$clienteId]['descricao'][] = "{$row['servico_nome']} para {$row['pet_nome']}";
            $clientes[$clienteId]['valor_total'] += (float) $row['valor'];
            $clientes[$clienteId]['petIds'][] = $row['petId'];

            $totalGeral += (float) $row['valor'];
        }

        if ($dados['taxi_dog']) {
            $totalGeral += (float) $dados['taxa_taxi_dog'];
            foreach ($clientes as &$cliente) {
                $cliente['valor_total'] += (float) $dados['taxa_taxi_dog'];
                $cliente['descricao'][] = "Táxi Dog";
            }
        }

        $totalGeral -= $desconto;
        $descontoPorCliente = count($clientes) > 0 ? $desconto / count($clientes) : 0;
        foreach ($clientes as &$cliente) {
            $cliente['valor_total'] -= $descontoPorCliente;
        }

        // Removida criação no financeiro pendente aqui. Apenas registra financeiro real.
        foreach ($clientes as $clienteNome => $info) {
            $financeiro = new Financeiro();
            $financeiro->setDescricao(implode(', ', $info['descricao']));
            $financeiro->setValor($info['valor_total']);
            $financeiro->setData(new \DateTime());
            $financeiro->setPetId($info['petIds'][0]);

            $this->getRepositorio(Financeiro::class)->save($this->session->get('userId'), $financeiro);
        }

        $agendamento = new Agendamento();
        $agendamento->setId($id);
        $agendamento->setData(new \DateTime($dados['data']));
        $agendamento->setHoraChegada($dados['horaChegada'] ? new \DateTime($dados['horaChegada']) : null);
        $agendamento->setHoraSaida($dados['horaSaida'] ? new \DateTime($dados['horaSaida']) : null);
        $agendamento->setTaxiDog((bool) $dados['taxi_dog']);
        $agendamento->setTaxaTaxiDog($dados['taxa_taxi_dog'] ?? null);
        $agendamento->setConcluido(true);
        $agendamento->setMetodoPagamento($metodoPagamento);
        $agendamento->setStatus('finalizado');

        $repo->update($this->session->get('userId'), $agendamento);

        // Remover registros pendentes antigos, caso existam
        try {
            $financeiroPendenteRepo = $this->getRepositorio(FinanceiroPendente::class);
            $financeiroPendenteRepo->removeByBaseId($this->session->get('userId'), $id);
        } catch (\Exception $e) {
            error_log("Erro ao remover registros pendentes: " . $e->getMessage());
        }

        return $this->json([
            'status' => 'success',
            'mensagem' => 'Pagamento concluído com sucesso!',
        ]);
    }


}