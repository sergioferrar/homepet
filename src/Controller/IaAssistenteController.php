<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use App\Service\TempDirManager;
use App\Service\DatabaseBkp;

/**
 * @Route("dashboard/ia/assistente")
 */
class IaAssistenteController extends DefaultController
{
    private $ems;

    public function __construct(
        ?Security              $security,
        ManagerRegistry        $managerRegistry,
        RequestStack           $request,
        TempDirManager         $tempDirManager,
        DatabaseBkp            $databaseBkp,
        EntityManagerInterface $em
    )
    {
        parent::__construct($security, $managerRegistry, $request, $tempDirManager, $databaseBkp);
        $this->ems = $em;
    }

    /**
     * @Route("/executar", name="ia_assistente_executar", methods={"POST"})
     */
    public function executar(Request $request): JsonResponse
    {
        try {
            $this->switchDB();
            $baseId = $this->getIdBase();
            $session = $request->getSession();

            $data = json_decode($request->getContent(), true);
            $comando = $data['comando'] ?? '';

            if (empty($comando)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Comando vazio'
                ]);
            }

            // Verificar se há contexto de conversa ativa
            $contexto = $session->get('ia_contexto', null);

            if ($contexto && isset($contexto['aguardando'])) {
                // Processar resposta do usuário
                $resultado = $this->processarRespostaFluxo($comando, $contexto, $baseId, $session);
            } else {
                // Analisar novo comando
                $analise = $this->analisarComando($comando);
                $resultado = $this->executarAcao($analise, $baseId, $session);

                // Log da ação
                $this->registrarLog($comando, $analise, $resultado, $baseId);
            }

            return new JsonResponse([
                'success' => true,
                'acao' => $resultado['acao'] ?? 'resposta',
                'message' => $resultado['message'],
                'dados' => $resultado['dados'] ?? null,
                'aguardando' => $resultado['aguardando'] ?? false
            ]);

        } catch (\Exception $e) {
            error_log("IA Assistente ERRO: " . $e->getMessage());
            error_log("IA Assistente TRACE: " . $e->getTraceAsString());

            return new JsonResponse([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage() . "\n\nArquivo: " . $e->getFile() . ":" . $e->getLine()
            ], 200); // Mudei para 200 para o frontend processar
        }
    }

    private function processarRespostaFluxo(string $resposta, array $contexto, int $baseId, $session): array
    {
        $aguardando = $contexto['aguardando'] ?? '';
        $dados = $contexto['dados'] ?? [];

        return $this->processarResposta($resposta, $aguardando, $dados, $baseId, $session);
    }

    private function processarResposta(string $resposta, string $etapa, array $dados, int $baseId, $session): array
    {

        switch ($etapa) {
            // Fluxo de Agendamento
            case 'confirmar_pet':
                if (stripos($resposta, 'sim') !== false || stripos($resposta, 's') !== false) {
                    $dados['pet_confirmado'] = true;
                    return $this->perguntarTaxiDog($dados, $session);
                } else {
                    $session->remove('ia_contexto');
                    return ['message' => '❌ Agendamento cancelado.'];
                }

            case 'taxi_dog':
                $dados['taxi_dog'] = (stripos($resposta, 'sim') !== false || stripos($resposta, 's') !== false);
                if ($dados['taxi_dog']) {
                    return $this->perguntarTaxaTaxi($dados, $session);
                } else {
                    return $this->perguntarPagamento($dados, $session);
                }

            case 'taxa_taxi':
                $taxa = floatval(preg_replace('/[^0-9.,]/', '', $resposta));
                $dados['taxa_taxi'] = $taxa;
                return $this->perguntarPagamento($dados, $session);

            case 'pagamento':
                $dados['pagamento'] = $this->identificarPagamento($resposta);
                return $this->perguntarObservacoes($dados, $session);

            case 'observacoes':
                $dados['observacoes'] = $resposta;
                return $this->finalizarAgendamento($dados, $baseId, $session);

            // Fluxo de Internação
            case 'motivo_internacao':
                $dados['motivo'] = $resposta;
                return $this->perguntarRiscoInternacao($dados, $session);

            case 'risco_internacao':
                $dados['risco'] = ucfirst(strtolower($resposta));
                return $this->perguntarBoxInternacao($dados, $session);

            case 'box_internacao':
                $dados['box'] = $resposta;
                return $this->perguntarPrognosticoInternacao($dados, $session);

            case 'prognostico_internacao':
                $dados['prognostico'] = ucfirst(strtolower($resposta));
                return $this->perguntarAltaPrevistaInternacao($dados, $session);

            case 'alta_prevista_internacao':
                // Processar data
                if (stripos($resposta, 'amanhã') !== false || stripos($resposta, 'amanha') !== false) {
                    $dados['alta_prevista'] = date('d/m/Y', strtotime('+1 day'));
                } elseif (preg_match('/(\d+)\s*dias?/i', $resposta, $matches)) {
                    $dias = (int)$matches[1];
                    $dados['alta_prevista'] = date('d/m/Y', strtotime("+{$dias} days"));
                } elseif (preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})/', $resposta, $matches)) {
                    $dados['alta_prevista'] = $matches[1];
                } else {
                    $dados['alta_prevista'] = $resposta;
                }
                return $this->perguntarAnotacoesInternacao($dados, $session);

            case 'anotacoes_internacao':
                $dados['anotacoes'] = $resposta;
                return $this->finalizarInternacao($dados, $baseId, $session);

            // Fluxo de Prescrição
            case 'nome_medicamento':
                $dados['medicamento'] = ucfirst(strtolower($resposta));
                return $this->perguntarDoseMedicamento($dados, $session);

            case 'dose_medicamento':
                $dados['dose'] = $resposta;
                return $this->perguntarFrequenciaMedicamento($dados, $session);

            case 'frequencia_medicamento':
                $dados['frequencia'] = $resposta;
                // Extrair horas da frequência (ex: "8 em 8 horas" -> 8)
                if (preg_match('/(\d+)\s*(?:em|a cada)\s*\d+\s*horas?/i', $resposta, $matches)) {
                    $dados['frequencia_horas'] = (int)$matches[1];
                } elseif (preg_match('/(\d+)\s*horas?/i', $resposta, $matches)) {
                    $dados['frequencia_horas'] = (int)$matches[1];
                } else {
                    $dados['frequencia_horas'] = 8; // padrão
                }
                return $this->perguntarDuracaoMedicamento($dados, $session);

            case 'duracao_medicamento':
                // Extrair número de dias
                if (preg_match('/(\d+)\s*dias?/i', $resposta, $matches)) {
                    $dados['duracao_dias'] = (int)$matches[1];
                } else {
                    $dados['duracao_dias'] = (int)$resposta;
                }
                return $this->perguntarViaMedicamento($dados, $session);

            case 'via_medicamento':
                $dados['via'] = ucfirst(strtolower($resposta));
                return $this->finalizarPrescricao($dados, $baseId, $session);

            // Fluxo de Atendimento/Consulta
            case 'nome_pet_atendimento':
                $petNome = trim($resposta);

                // Buscar pet
                $pet = $this->ems->getRepository(\App\Entity\Pet::class)
                    ->createQueryBuilder('p')
                    ->where('LOWER(p.nome) = :nome')
                    ->andWhere('p.estabelecimentoId = :baseId')
                    ->setParameter('nome', strtolower($petNome))
                    ->setParameter('baseId', $baseId)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if (!$pet) {
                    $session->remove('ia_contexto');
                    return ['message' => "❌ Pet **{$petNome}** não encontrado.\n\nVerifique o nome e tente novamente."];
                }

                $dados['pet_id'] = $pet->getId();
                $dados['pet_nome'] = $pet->getNome();
                $dados['cliente_id'] = $pet->getDono_Id();

                $session->set('ia_contexto', [
                    'aguardando' => 'tipo_atendimento',
                    'dados' => $dados
                ]);

                return [
                    'message' => "🩺 Atendimento para **{$pet->getNome()}**\n\n📋 Qual o tipo de atendimento?\n\nExemplo: Consulta, Retorno, Emergência, Check-up",
                    'aguardando' => true
                ];

            case 'tipo_atendimento':
                $dados['tipo_atendimento'] = ucfirst(strtolower($resposta));
                return $this->perguntarObservacoesAtendimento($dados, $session);

            case 'observacoes_atendimento':
                $dados['observacoes_atendimento'] = $resposta;
                return $this->perguntarAnamneseAtendimento($dados, $session);

            case 'anamnese_atendimento':
                $dados['anamnese'] = $resposta;
                return $this->finalizarAtendimento($dados, $baseId, $session);

            // Fluxo de Receita
            case 'conteudo_receita':
                $dados['conteudo_receita'] = $resposta;
                return $this->perguntarResumoReceita($dados, $session);

            case 'resumo_receita':
                $dados['resumo_receita'] = $resposta;
                return $this->finalizarReceita($dados, $baseId, $session);

            default:
                $session->remove('ia_contexto');
                return ['message' => '❌ Erro no fluxo de conversa.'];
        }
    }

    private function perguntarTaxiDog(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'taxi_dog',
            'dados' => $dados
        ]);

        return [
            'message' => "🚗 Precisa de Taxi Dog?\n\nResponda: Sim ou Não",
            'aguardando' => true
        ];
    }

    private function perguntarTaxaTaxi(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'taxa_taxi',
            'dados' => $dados
        ]);

        return [
            'message' => "💰 Qual o valor da taxa do Taxi Dog?\n\nExemplo: R$ 20,00",
            'aguardando' => true
        ];
    }

    private function perguntarPagamento(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'pagamento',
            'dados' => $dados
        ]);

        return [
            'message' => "💳 Como será o pagamento?\n\n• Dinheiro\n• PIX\n• Cartão Crédito\n• Cartão Débito\n• Pendente (Fiado)",
            'aguardando' => true
        ];
    }

    private function perguntarObservacoes(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'observacoes',
            'dados' => $dados
        ]);

        return [
            'message' => "📝 Alguma observação especial?\n\n(Digite 'não' se não houver)",
            'aguardando' => true
        ];
    }

    private function identificarPagamento(string $resposta): string
    {
        $resposta = strtolower($resposta);

        if (strpos($resposta, 'dinheiro') !== false) return 'dinheiro';
        if (strpos($resposta, 'pix') !== false) return 'pix';
        if (strpos($resposta, 'credito') !== false || strpos($resposta, 'crédito') !== false) return 'credito';
        if (strpos($resposta, 'debito') !== false || strpos($resposta, 'débito') !== false) return 'debito';
        if (strpos($resposta, 'pendente') !== false || strpos($resposta, 'fiado') !== false) return 'pendente';

        return 'pendente';
    }

    private function finalizarAgendamento(array $dados, int $baseId, $session): array
    {
        try {
            // Buscar pet
            $pet = $this->ems->getRepository(\App\Entity\Pet::class)->find($dados['pet_id']);

            if (!$pet) {
                $session->remove('ia_contexto');
                return ['message' => '❌ Pet não encontrado.'];
            }

            // Criar data e hora
            $dataHora = \DateTime::createFromFormat('d/m/Y H:i', $dados['data'] . ' ' . $dados['hora']);

            // Criar agendamento
            $agendamento = new \App\Entity\Agendamento();
            $agendamento->setData($dataHora);
            $agendamento->setDonoId($pet->getDono_Id());
            $agendamento->setEstabelecimentoId($baseId);
            $agendamento->setStatus('aguardando');
            $agendamento->setConcluido(false);
            $agendamento->setPronto(false);
            $agendamento->setMetodoPagamento($dados['pagamento']);
            $agendamento->setTaxiDog($dados['taxi_dog']);

            if ($dados['taxi_dog'] && isset($dados['taxa_taxi'])) {
                $agendamento->setTaxaTaxiDog($dados['taxa_taxi']);
            }

            $this->ems->persist($agendamento);
            $this->ems->flush();

            // Criar relação com serviço
            if (isset($dados['servico_id'])) {
                $agendamentoPetServico = new \App\Entity\AgendamentoPetServico();
                $agendamentoPetServico->setAgendamentoId($agendamento->getId());
                $agendamentoPetServico->setPetId($pet->getId());
                $agendamentoPetServico->setServicoId($dados['servico_id']);
                $agendamentoPetServico->setEstabelecimentoId($baseId);

                $this->ems->persist($agendamentoPetServico);
                $this->ems->flush();
            }

            $session->remove('ia_contexto');

            $message = "✅ Agendamento criado com sucesso!\n\n";
            $message .= "🐕 Pet: {$pet->getNome()}\n";
            $message .= "✂️ Serviço: {$dados['servico']}\n";
            $message .= "📆 Data: {$dados['data']}\n";
            $message .= "🕐 Horário: {$dados['hora']}\n";
            $message .= "🚗 Taxi Dog: " . ($dados['taxi_dog'] ? 'Sim' : 'Não') . "\n";
            if ($dados['taxi_dog'] && isset($dados['taxa_taxi'])) {
                $message .= "💰 Taxa Taxi: R$ " . number_format($dados['taxa_taxi'], 2, ',', '.') . "\n";
            }
            $message .= "💳 Pagamento: " . ucfirst($dados['pagamento']) . "\n";
            if (!empty($dados['observacoes']) && strtolower($dados['observacoes']) !== 'não') {
                $message .= "📝 Obs: {$dados['observacoes']}\n";
            }
            $message .= "\n📋 ID: #{$agendamento->getId()}";

            return [
                'message' => $message,
                'acao' => 'agendar',
                'dados' => ['agendamento_id' => $agendamento->getId()]
            ];

        } catch (\Exception $e) {
            $session->remove('ia_contexto');
            return ['message' => "❌ Erro ao criar agendamento: " . $e->getMessage()];
        }
    }

    private function analisarComando(string $comando): array
    {
        $comandoOriginal = $comando;
        $comando = mb_strtolower($comando, 'UTF-8');

        // Normalizar: remover acentos e caracteres especiais
        $comando = $this->normalizarTexto($comando);

        // Corrigir erros comuns de digitação usando distância de Levenshtein
        $comando = $this->corrigirErrosDigitacao($comando);

        // Detectar tipo de ação
        $acoes = [
            'cadastrar' => ['cadastrar', 'cadastra', 'criar', 'adicionar', 'registrar', 'novo'],
            'agendar' => ['agendar', 'marcar', 'reservar', 'agenda'],
            'internar' => ['internar', 'internação', 'internacao', 'hospitalizar'],
            'alta' => ['alta', 'liberar', 'liberação', 'liberacao', 'dar alta'],
            'prescrever' => ['prescrever', 'prescrição', 'prescricao', 'receitar', 'medicar', 'prescreve', 'medicamento', 'remedio', 'remédio', 'dar medicamento', 'aplicar medicamento', 'agendar medicamento', 'agenda medicamento'],
            'consulta' => ['consulta', 'atender', 'atendimento', 'consultar'],
            'vacinar' => ['vacinar', 'vacina', 'vacinação', 'vacinacao', 'aplicar vacina'],
            'obito' => ['óbito', 'obito', 'faleceu', 'morreu', 'morte'],
            'orcamento' => ['orçamento', 'orcamento', 'cotação', 'cotacao', 'preço', 'preco'],
            'venda' => ['vender', 'venda', 'comprar', 'pdv', 'vende'],
            'buscar' => ['buscar', 'procurar', 'encontrar', 'listar', 'mostrar', 'ver', 'exibir'],
            'debito' => ['débito', 'debito', 'dívida', 'divida', 'deve', 'pendente', 'fiado'],
            'historico' => ['histórico', 'historico', 'ficha', 'prontuário', 'prontuario'],
            'relatorio' => ['relatório', 'relatorio', 'resumo', 'balanço', 'balanco'],
            'ajuda' => ['ajuda', 'help', 'comandos', 'o que você faz', 'que faz', 'funcionalidades']
        ];

        // Serviços que devem ser agendados (não são atendimento clínico)
        $servicosAgendamento = ['banho', 'tosa', 'hospedagem', 'hotel', 'creche', 'day care'];

        $acaoDetectada = 'desconhecida';

        // Verifica se é um agendamento de serviço (banho, tosa, hospedagem)
        $isAgendamentoServico = false;
        foreach ($servicosAgendamento as $servico) {
            if (strpos($comando, $servico) !== false) {
                $isAgendamentoServico = true;
                break;
            }
        }

        // Se tem palavra de agendamento + serviço, é agendamento
        if ($isAgendamentoServico && (strpos($comando, 'agendar') !== false || strpos($comando, 'marcar') !== false || strpos($comando, 'reservar') !== false)) {
            $acaoDetectada = 'agendar';
        } else {
            // Caso contrário, detecta normalmente
            foreach ($acoes as $acao => $palavras) {
                foreach ($palavras as $palavra) {
                    if (strpos($comando, $palavra) !== false) {
                        $acaoDetectada = $acao;
                        break 2;
                    }
                }
            }
        }

        // Extrair entidades (nome, data, hora, etc)
        $entidades = $this->extrairEntidades($comando);

        return [
            'acao' => $acaoDetectada,
            'comando_original' => $comando,
            'entidades' => $entidades
        ];
    }

    private function extrairEntidades(string $comando): array
    {
        $entidades = [];

        // Extrair datas
        if (preg_match('/(hoje|amanhã|amanha)/i', $comando, $matches)) {
            $quando = strtolower($matches[1]);
            if ($quando === 'hoje') {
                $entidades['quando'] = 'hoje';
                $entidades['data'] = date('d/m/Y');
            } else {
                $entidades['quando'] = 'amanhã';
                $entidades['data'] = date('d/m/Y', strtotime('+1 day'));
            }
        }

        if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})/', $comando, $matches)) {
            $entidades['data'] = $matches[1];
            $entidades['quando'] = $matches[1];
        }

        // Extrair horários
        if (preg_match('/(\d{1,2}):?(\d{2})?\s*(h|horas)?/i', $comando, $matches)) {
            $hora = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $minuto = isset($matches[2]) && $matches[2] !== '' ? $matches[2] : '00';
            $entidades['hora'] = $hora . ':' . $minuto;
        }

        // Extrair nomes - mais flexível e case-insensitive
        // Tenta "pet Nome" primeiro (mais específico)
        if (preg_match('/pet\s+([a-zà-úA-ZÀ-Ú]+)/i', $comando, $matches)) {
            $nome = trim($matches[1]);
            $palavrasTempo = ['hoje', 'amanha', 'amanhã', 'ontem', 'dia'];
            if (!in_array(strtolower($nome), $palavrasTempo)) {
                $entidades['nome'] = ucfirst(strtolower($nome));
            }
        }

        // Tenta "para o/a Nome" ou "pro/pra Nome"
        if (!isset($entidades['nome']) && preg_match('/(?:para|pro|pra)\s+(?:o|a)?\s*([a-zà-úA-ZÀ-Ú]+)/i', $comando, $matches)) {
            $nome = trim($matches[1]);
            $palavrasTempo = ['hoje', 'amanha', 'amanhã', 'ontem', 'dia', 'pet'];
            if (!in_array(strtolower($nome), $palavrasTempo)) {
                $entidades['nome'] = ucfirst(strtolower($nome));
            }
        }

        // Tenta "do/da Nome"
        if (!isset($entidades['nome']) && preg_match('/(?:do|da)\s+([a-zà-úA-ZÀ-Ú]+)/i', $comando, $matches)) {
            $nome = trim($matches[1]);
            $palavrasTempo = ['hoje', 'amanha', 'amanhã', 'ontem', 'dia', 'pet'];
            if (!in_array(strtolower($nome), $palavrasTempo)) {
                $entidades['nome'] = ucfirst(strtolower($nome));
            }
        }

        // Extrair tipo de serviço
        $servicos = [
            'banho e tosa' => 'Banho e Tosa',
            'banho' => 'Banho',
            'tosa' => 'Tosa',
            'consulta' => 'Consulta',
            'cirurgia' => 'Cirurgia',
            'exame' => 'Exame',
            'vacina' => 'Vacina',
            'internação' => 'Internação',
            'internacao' => 'Internação'
        ];

        foreach ($servicos as $key => $nome) {
            if (stripos($comando, $key) !== false) {
                $entidades['servico'] = $nome;
                break;
            }
        }

        return $entidades;
    }

    private function executarAcao(array $analise, int $baseId, $session): array
    {
        switch ($analise['acao']) {
            case 'cadastrar':
                return $this->cadastrarClientePet($analise, $baseId);

            case 'agendar':
                return $this->iniciarAgendamento($analise, $baseId, $session);

            case 'internar':
                return $this->internarPet($analise, $baseId, $session);

            case 'alta':
                return $this->darAlta($analise, $baseId);

            case 'prescrever':
                return $this->prescreverMedicamento($analise, $baseId, $session);

            case 'consulta':
                return $this->registrarAtendimento($analise, $baseId, $session);

            case 'vacinar':
                return $this->aplicarVacina($analise, $baseId);

            case 'obito':
                return $this->registrarObito($analise, $baseId);

            case 'orcamento':
                return $this->criarOrcamento($analise, $baseId);

            case 'venda':
                return $this->registrarVenda($analise, $baseId);

            case 'buscar':
                return $this->buscarInformacao($analise, $baseId);

            case 'debito':
                return $this->consultarDebitos($analise, $baseId);

            case 'historico':
                return $this->consultarHistorico($analise, $baseId);

            case 'relatorio':
                return $this->gerarRelatorio($analise, $baseId);

            case 'ajuda':
                return $this->mostrarAjuda();

            default:
                return $this->mostrarAjuda();
        }
    }

    private function cadastrarClientePet(array $analise, int $baseId): array
    {
        $comando = $analise['comando_original'];

        try {
            // Extrair informações do comando
            $info = $this->extrairInfoCadastro($comando);

            if (empty($info['tutor'])) {
                return [
                    'message' => '❌ Nome do tutor não encontrado.\n\nExemplo: "cadastrar pet Cacal do tutor Lulu Santos, telefone 31999887766"'
                ];
            }

            // Verificar se cliente já existe
            $clienteExistente = $this->ems->getRepository(\App\Entity\Cliente::class)
                ->createQueryBuilder('c')
                ->where('LOWER(c.nome) = :nome')
                ->andWhere('c.estabelecimentoId = :estab')
                ->setParameter('nome', strtolower($info['tutor']))
                ->setParameter('estab', $baseId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$clienteExistente) {
                // Criar cliente
                $cliente = new \App\Entity\Cliente();
                $cliente->setNome($info['tutor']);
                $cliente->setEstabelecimentoId($baseId);

                if (!empty($info['telefone'])) {
                    $cliente->setTelefone($info['telefone']);
                }
                if (!empty($info['rua'])) {
                    $cliente->setRua($info['rua']);
                }
                if (!empty($info['email'])) {
                    $cliente->setEmail($info['email']);
                }

                $this->ems->persist($cliente);
                $this->ems->flush();

                $clienteId = $cliente->getId();
                $mensagemCliente = "✅ Cliente cadastrado: {$info['tutor']} (ID: #{$clienteId})\n";
            } else {
                $clienteId = $clienteExistente->getId();
                $mensagemCliente = "ℹ️ Cliente já existe: {$info['tutor']} (ID: #{$clienteId})\n";
            }

            // Cadastrar pet se informado
            if (!empty($info['pet'])) {
                $pet = new \App\Entity\Pet();
                $pet->setNome($info['pet']);
                $pet->setDono_Id((string)$clienteId);
                $pet->setEstabelecimentoId($baseId);

                if (!empty($info['especie'])) {
                    $pet->setEspecie($info['especie']);
                }
                if (!empty($info['raca'])) {
                    $pet->setRaca($info['raca']);
                }

                $this->ems->persist($pet);
                $this->ems->flush();

                $mensagemPet = "✅ Pet cadastrado: {$info['pet']} (ID: #{$pet->getId()})\n";
            } else {
                $mensagemPet = "";
            }

            $message = "🎉 Cadastro realizado com sucesso!\n\n";
            $message .= $mensagemCliente;
            $message .= $mensagemPet;

            if (!empty($info['telefone'])) {
                $message .= "📞 Telefone: {$info['telefone']}\n";
            }
            if (!empty($info['rua'])) {
                $message .= "📍 Endereço: {$info['rua']}\n";
            }

            return [
                'message' => $message,
                'acao' => 'cadastrar',
                'dados' => [
                    'cliente_id' => $clienteId,
                    'pet_id' => isset($pet) ? $pet->getId() : null
                ]
            ];

        } catch (\Exception $e) {
            return [
                'message' => "❌ Erro ao cadastrar: " . $e->getMessage()
            ];
        }
    }

    private function extrairInfoCadastro(string $comando): array
    {
        $info = [];

        // Extrair nome do pet
        if (preg_match('/pet\s+(?:é\s+)?([A-Za-zà-úÀ-Ú]+)/i', $comando, $matches)) {
            $info['pet'] = trim($matches[1]);
        }

        // Extrair nome do tutor
        if (preg_match('/tutor\s+(?:é\s+)?([A-Za-zà-úÀ-Ú\s]+?)(?:,|do|telefone|rua|email|$)/i', $comando, $matches)) {
            $info['tutor'] = trim($matches[1]);
        }

        // Extrair telefone
        if (preg_match('/telefone\s*:?\s*(\d{10,11})/', $comando, $matches)) {
            $info['telefone'] = $matches[1];
        }

        // Extrair rua
        if (preg_match('/rua\s+(?:dele\s+)?(?:é\s+)?([A-Za-z0-9\s]+?)(?:,|telefone|email|$)/i', $comando, $matches)) {
            $info['rua'] = trim($matches[1]);
        }

        // Extrair email
        if (preg_match('/email\s*:?\s*([^\s,]+@[^\s,]+)/i', $comando, $matches)) {
            $info['email'] = $matches[1];
        }

        // Extrair espécie
        $especies = ['cachorro', 'gato', 'coelho', 'hamster', 'passaro', 'pássaro'];
        foreach ($especies as $especie) {
            if (stripos($comando, $especie) !== false) {
                $info['especie'] = ucfirst($especie);
                break;
            }
        }

        return $info;
    }

    private function iniciarAgendamento(array $analise, int $baseId, $session): array
    {
        $entidades = $analise['entidades'];

        $servico = $entidades['servico'] ?? 'Serviço';
        $nomeBusca = $entidades['nome'] ?? null;
        $dataStr = $entidades['data'] ?? null;
        $hora = $entidades['hora'] ?? '09:00';

        if (!$nomeBusca) {
            return [
                'message' => '❌ Por favor, especifique o nome do pet ou tutor.\nExemplo: "agendar banho para Rex amanhã às 14h"'
            ];
        }

        if (!$dataStr) {
            return [
                'message' => '❌ Por favor, especifique a data.\nExemplo: "agendar banho para Rex amanhã às 14h"'
            ];
        }

        try {
            // Buscar pet pelo nome
            $pet = $this->ems->getRepository(\App\Entity\Pet::class)
                ->createQueryBuilder('p')
                ->where('LOWER(p.nome) LIKE :nome')
                ->andWhere('p.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            // Se não encontrou pet, buscar pelo nome do tutor
            if (!$pet) {
                $cliente = $this->ems->getRepository(\App\Entity\Cliente::class)
                    ->createQueryBuilder('c')
                    ->where('LOWER(c.nome) LIKE :nome')
                    ->andWhere('c.estabelecimentoId = :estab')
                    ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                    ->setParameter('estab', $baseId)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($cliente) {
                    // Buscar pets do cliente
                    $pets = $this->ems->getRepository(\App\Entity\Pet::class)
                        ->createQueryBuilder('p')
                        ->where('p.dono_id = :donoId')
                        ->andWhere('p.estabelecimentoId = :estab')
                        ->setParameter('donoId', $cliente->getId())
                        ->setParameter('estab', $baseId)
                        ->getQuery()
                        ->getResult();

                    if (count($pets) > 0) {
                        $pet = $pets[0]; // Pega o primeiro pet
                    }
                }
            }

            if (!$pet) {
                return [
                    'message' => "❌ Pet ou tutor '{$nomeBusca}' não encontrado.\nVerifique o nome e tente novamente."
                ];
            }

            // Buscar serviço
            $servicoObj = $this->ems->getRepository(\App\Entity\Servico::class)
                ->createQueryBuilder('s')
                ->where('LOWER(s.nome) LIKE :nome')
                ->andWhere('s.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($servico) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            // Iniciar fluxo de perguntas
            $dados = [
                'pet_id' => $pet->getId(),
                'pet_nome' => $pet->getNome(),
                'servico' => $servico,
                'servico_id' => $servicoObj ? $servicoObj->getId() : null,
                'data' => $dataStr,
                'hora' => $hora
            ];

            $session->set('ia_contexto', [
                'aguardando_resposta' => true,
                'etapa' => 'confirmar_pet',
                'dados' => $dados
            ]);

            return [
                'message' => "🐕 Confirma agendamento para o pet **{$pet->getNome()}**?\n\n✂️ Serviço: {$servico}\n📆 Data: {$dataStr}\n🕐 Horário: {$hora}\n\nResponda: Sim ou Não",
                'aguardando' => true,
                'acao' => 'agendar'
            ];

        } catch (\Exception $e) {
            return [
                'message' => "❌ Erro: " . $e->getMessage()
            ];
        }
    }

    private function agendarServico(array $analise, int $baseId): array
    {
        $entidades = $analise['entidades'];

        $servico = $entidades['servico'] ?? 'Serviço';
        $nomeBusca = $entidades['nome'] ?? null;
        $dataStr = $entidades['data'] ?? null;
        $hora = $entidades['hora'] ?? '09:00';

        if (!$nomeBusca) {
            return [
                'message' => '❌ Por favor, especifique o nome do pet ou cliente.\nExemplo: "agendar banho para Rex amanhã às 14h"'
            ];
        }

        if (!$dataStr) {
            return [
                'message' => '❌ Por favor, especifique a data.\nExemplo: "agendar banho para Rex amanhã às 14h"'
            ];
        }

        try {
            // Buscar pet pelo nome
            $pet = $this->ems->getRepository(\App\Entity\Pet::class)
                ->createQueryBuilder('p')
                ->where('LOWER(p.nome) LIKE :nome')
                ->andWhere('p.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$pet) {
                // Tentar buscar cliente
                $cliente = $this->ems->getRepository(\App\Entity\Cliente::class)
                    ->createQueryBuilder('c')
                    ->where('LOWER(c.nome) LIKE :nome')
                    ->andWhere('c.estabelecimentoId = :estab')
                    ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                    ->setParameter('estab', $baseId)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if (!$cliente) {
                    // Sugerir pets/clientes similares
                    $sugestoes = $this->ems->getRepository(\App\Entity\Pet::class)
                        ->createQueryBuilder('p')
                        ->where('p.estabelecimentoId = :estab')
                        ->setParameter('estab', $baseId)
                        ->setMaxResults(5)
                        ->getQuery()
                        ->getResult();

                    $message = "❌ Pet ou cliente '{$nomeBusca}' não encontrado.\n\n";

                    if (count($sugestoes) > 0) {
                        $message .= "💡 Pets disponíveis:\n";
                        foreach ($sugestoes as $sug) {
                            $message .= "• {$sug->getNome()}\n";
                        }
                        $message .= "\nTente: \"agendar banho para {$sugestoes[0]->getNome()} amanhã às 14h\"";
                    } else {
                        $message .= "Cadastre um pet primeiro para poder agendar.";
                    }

                    return ['message' => $message];
                }

                $donoId = $cliente->getId();
                $nomeCompleto = $cliente->getNome();
            } else {
                $donoId = $pet->getDono_Id();
                $nomeCompleto = $pet->getNome();
            }

            // Buscar serviço pelo nome
            $servicoObj = $this->ems->getRepository(\App\Entity\Servico::class)
                ->createQueryBuilder('s')
                ->where('LOWER(s.nome) LIKE :nome')
                ->andWhere('s.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($servico) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            // Criar data e hora
            $dataHora = \DateTime::createFromFormat('d/m/Y H:i', $dataStr . ' ' . $hora);
            if (!$dataHora) {
                $dataHora = new \DateTime('tomorrow ' . $hora);
            }

            // Criar agendamento
            $agendamento = new \App\Entity\Agendamento();
            $agendamento->setData($dataHora);
            $agendamento->setDonoId($donoId);
            $agendamento->setEstabelecimentoId($baseId);
            $agendamento->setStatus('aguardando');
            $agendamento->setConcluido(false);
            $agendamento->setPronto(false);

            $this->ems->persist($agendamento);

            // Flush para obter o ID do agendamento
            $this->ems->flush();

            // Criar relação com serviço se encontrado
            if ($servicoObj && $pet) {
                $agendamentoPetServico = new \App\Entity\AgendamentoPetServico();
                $agendamentoPetServico->setAgendamentoId($agendamento->getId());
                $agendamentoPetServico->setPetId($pet->getId());
                $agendamentoPetServico->setServicoId($servicoObj->getId());
                $agendamentoPetServico->setEstabelecimentoId($baseId);

                $this->ems->persist($agendamentoPetServico);
            }

            $this->ems->flush();

            $message = "✅ Agendamento criado com sucesso!\n\n";
            $message .= "🐕 Pet/Cliente: {$nomeCompleto}\n";
            $message .= "✂️ Serviço: {$servico}\n";
            $message .= "📆 Data: " . $dataHora->format('d/m/Y') . "\n";
            $message .= "🕐 Horário: " . $dataHora->format('H:i') . "\n";
            $message .= "📋 ID do Agendamento: #{$agendamento->getId()}\n\n";
            $message .= "✨ Acesse a tela de Agendamentos para ver mais detalhes.";

            return [
                'message' => $message,
                'dados' => [
                    'agendamento_id' => $agendamento->getId(),
                    'pet_nome' => $nomeCompleto,
                    'data' => $dataHora->format('d/m/Y H:i')
                ]
            ];

        } catch (\Exception $e) {
            return [
                'message' => "❌ Erro ao criar agendamento: " . $e->getMessage()
            ];
        }
    }

    private function internarPet(array $analise, int $baseId, $session): array
    {
        $entidades = $analise['entidades'];
        $nomeBusca = $entidades['nome'] ?? null;

        if (!$nomeBusca) {
            return ['message' => '❌ Especifique o nome do pet.\nExemplo: "internar Harry" ou "registrar internação do Rex"'];
        }

        try {
            // Buscar pet
            $pet = $this->ems->getRepository(\App\Entity\Pet::class)
                ->createQueryBuilder('p')
                ->where('LOWER(p.nome) LIKE :nome')
                ->andWhere('p.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$pet) {
                return ['message' => "❌ Pet '{$nomeBusca}' não encontrado."];
            }

            // Iniciar fluxo de perguntas
            $dados = [
                'pet_id' => $pet->getId(),
                'pet_nome' => $pet->getNome(),
                'dono_id' => $pet->getDono_Id()
            ];

            return $this->perguntarMotivoInternacao($dados, $session);

        } catch (\Exception $e) {
            return ['message' => "❌ Erro: " . $e->getMessage()];
        }
    }

    private function perguntarMotivoInternacao(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'motivo_internacao',
            'dados' => $dados
        ]);

        return [
            'message' => "🏥 Internação do pet **{$dados['pet_nome']}**\n\n📝 Qual o motivo da internação?",
            'aguardando' => true
        ];
    }

    private function perguntarRiscoInternacao(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'risco_internacao',
            'dados' => $dados
        ]);

        return [
            'message' => "⚠️ Qual o nível de risco?\n\n• Baixo\n• Médio\n• Alto\n• Crítico",
            'aguardando' => true
        ];
    }

    private function perguntarBoxInternacao(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'box_internacao',
            'dados' => $dados
        ]);

        return [
            'message' => "🏠 Qual o número do box?\n\nExemplo: Box 1, Box 2, etc.",
            'aguardando' => true
        ];
    }

    private function perguntarPrognosticoInternacao(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'prognostico_internacao',
            'dados' => $dados
        ]);

        return [
            'message' => "🔮 Qual o prognóstico?\n\n• Excelente\n• Bom\n• Regular\n• Reservado\n• Grave",
            'aguardando' => true
        ];
    }

    private function perguntarAltaPrevistaInternacao(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'alta_prevista_internacao',
            'dados' => $dados
        ]);

        return [
            'message' => "📅 Qual a data prevista para alta?\n\nExemplo: 05/11/2025 ou 'amanhã' ou '3 dias'",
            'aguardando' => true
        ];
    }

    private function perguntarAnotacoesInternacao(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'anotacoes_internacao',
            'dados' => $dados
        ]);

        return [
            'message' => "📝 Alguma anotação adicional?\n\n(Digite 'não' se não houver)",
            'aguardando' => true
        ];
    }

    private function finalizarInternacao(array $dados, int $baseId, $session): array
    {
        try {
            // Criar internação
            $internacao = new \App\Entity\Internacao();
            $internacao->setPetId($dados['pet_id']);
            $internacao->setDonoId($dados['dono_id']);
            $internacao->setEstabelecimentoId($baseId);
            $internacao->setDataInicio(new \DateTime());
            $internacao->setStatus('ativa');
            $internacao->setMotivo($dados['motivo']);
            $internacao->setDiagnostico($dados['motivo']);

            // Campos adicionais
            if (isset($dados['risco'])) {
                $internacao->setRisco($dados['risco']);
                $internacao->setSituacao($dados['risco']);
            }
            if (isset($dados['box'])) {
                $internacao->setBox($dados['box']);
            }
            if (isset($dados['prognostico'])) {
                $internacao->setPrognostico($dados['prognostico']);
            }
            if (isset($dados['alta_prevista'])) {
                // Converter string de data para DateTime
                $altaPrevista = \DateTime::createFromFormat('d/m/Y', $dados['alta_prevista']);
                if ($altaPrevista) {
                    $internacao->setAltaPrevista($altaPrevista);
                }
            }
            if (isset($dados['anotacoes']) && strtolower($dados['anotacoes']) !== 'não') {
                $internacao->setAnotacoes($dados['anotacoes']);
            }

            $this->ems->persist($internacao);
            $this->ems->flush();

            // Criar evento de internação na timeline
            $internacaoRepo = $this->ems->getRepository(\App\Entity\Internacao::class);
            $descricaoEvento = "Motivo: {$dados['motivo']}";
            if (isset($dados['risco'])) {
                $descricaoEvento .= " | Risco: {$dados['risco']}";
            }
            if (isset($dados['box'])) {
                $descricaoEvento .= " | Box: {$dados['box']}";
            }
            if (isset($dados['prognostico'])) {
                $descricaoEvento .= " | Prognóstico: {$dados['prognostico']}";
            }

            $internacaoRepo->inserirEvento(
                $baseId,
                $internacao->getId(),
                $dados['pet_id'],
                'internacao',
                'Internação Iniciada',
                $descricaoEvento,
                new \DateTime(),
                'ativo'
            );

            $session->remove('ia_contexto');

            $message = "✅ Internação registrada com sucesso!\n\n";
            $message .= "🐕 Pet: {$dados['pet_nome']}\n";
            $message .= "📋 ID Internação: #{$internacao->getId()}\n";
            $message .= "📅 Data Início: " . $internacao->getDataInicio()->format('d/m/Y H:i') . "\n";
            $message .= "🩺 Motivo: {$dados['motivo']}\n";
            if (isset($dados['risco'])) {
                $message .= "⚠️ Risco: {$dados['risco']}\n";
            }
            if (isset($dados['box'])) {
                $message .= "🏠 Box: {$dados['box']}\n";
            }
            if (isset($dados['prognostico'])) {
                $message .= "🔮 Prognóstico: {$dados['prognostico']}\n";
            }
            if (isset($dados['alta_prevista'])) {
                $message .= "📅 Alta Prevista: {$dados['alta_prevista']}\n";
            }
            if (isset($dados['anotacoes']) && strtolower($dados['anotacoes']) !== 'não') {
                $message .= "📝 Anotações: {$dados['anotacoes']}\n";
            }
            $message .= "📊 Status: Ativo";

            return [
                'message' => $message,
                'acao' => 'internar',
                'dados' => ['internacao_id' => $internacao->getId()]
            ];

        } catch (\Exception $e) {
            $session->remove('ia_contexto');
            return ['message' => "❌ Erro ao criar internação: " . $e->getMessage()];
        }
    }

    private function darAlta(array $analise, int $baseId): array
    {
        $entidades = $analise['entidades'];
        $nomeBusca = $entidades['nome'] ?? null;

        if (!$nomeBusca) {
            return ['message' => '❌ Especifique o nome do pet.\nExemplo: "dar alta para Rex"'];
        }

        try {
            // Buscar pet
            $pet = $this->ems->getRepository(\App\Entity\Pet::class)
                ->createQueryBuilder('p')
                ->where('LOWER(p.nome) LIKE :nome')
                ->andWhere('p.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$pet) {
                return ['message' => "❌ Pet '{$nomeBusca}' não encontrado."];
            }

            // Buscar internação ativa
            $internacao = $this->ems->getRepository(\App\Entity\Internacao::class)
                ->createQueryBuilder('i')
                ->where('i.pet_id = :petId')
                ->andWhere('i.estabelecimento_id = :estab')
                ->andWhere('i.status = :status')
                ->setParameter('petId', $pet->getId())
                ->setParameter('estab', $baseId)
                ->setParameter('status', 'ativo')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$internacao) {
                return ['message' => "❌ {$pet->getNome()} não está internado."];
            }

            // Dar alta
            $internacao->setStatus('alta');
            $this->ems->flush();

            $message = "✅ Alta médica registrada!\n\n";
            $message .= "🐕 Pet: {$pet->getNome()}\n";
            $message .= "📋 ID Internação: #{$internacao->getId()}\n";
            $message .= "📅 Alta em: " . (new \DateTime())->format('d/m/Y H:i') . "\n";

            return ['message' => $message];

        } catch (\Exception $e) {
            return ['message' => "❌ Erro: " . $e->getMessage()];
        }
    }

    private function prescreverMedicamento(array $analise, int $baseId, $session): array
    {
        $entidades = $analise['entidades'];
        $nomeBusca = $entidades['nome'] ?? null;
        $comando = $analise['comando_original'];

        if (!$nomeBusca) {
            return ['message' => '❌ Especifique o nome do pet.\nExemplo: "prescrever dipirona para Luna"'];
        }

        try {
            // Buscar pet
            $pet = $this->ems->getRepository(\App\Entity\Pet::class)
                ->createQueryBuilder('p')
                ->where('LOWER(p.nome) LIKE :nome')
                ->andWhere('p.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$pet) {
                return ['message' => "❌ Pet '{$nomeBusca}' não encontrado."];
            }

            // Buscar internação ativa
            $internacao = $this->ems->getRepository(\App\Entity\Internacao::class)
                ->createQueryBuilder('i')
                ->where('i.pet_id = :petId')
                ->andWhere('i.estabelecimento_id = :estab')
                ->andWhere('i.status = :status')
                ->setParameter('petId', $pet->getId())
                ->setParameter('estab', $baseId)
                ->setParameter('status', 'ativa')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$internacao) {
                return ['message' => "❌ {$pet->getNome()} não está internado. Interne o pet primeiro."];
            }

            // Extrair medicamento - tenta vários padrões
            $medicamento = '';

            // Padrão: "prescrever/medicar/dar MEDICAMENTO"
            if (preg_match('/(?:prescrever|prescreve|medicar|dar|aplicar|agendar|agenda)\s+(?:medicamento\s+)?(\w+)/i', $comando, $matches)) {
                $medicamento = ucfirst(strtolower($matches[1]));
            }

            // Se não encontrou, pede o nome
            if (!$medicamento || in_array(strtolower($medicamento), ['medicamento', 'remedio', 'remédio', 'pro', 'para', 'do', 'da'])) {
                $dados = [
                    'pet_id' => $pet->getId(),
                    'pet_nome' => $pet->getNome(),
                    'internacao_id' => $internacao->getId()
                ];

                $session->set('ia_contexto', [
                    'aguardando_resposta' => true,
                    'etapa' => 'nome_medicamento',
                    'dados' => $dados
                ]);

                return [
                    'message' => "💊 Qual medicamento deseja prescrever para **{$pet->getNome()}**?\n\nExemplo: Dipirona, Amoxicilina, Meloxicam",
                    'aguardando' => true
                ];
            }

            // Iniciar fluxo de perguntas
            $dados = [
                'pet_id' => $pet->getId(),
                'pet_nome' => $pet->getNome(),
                'internacao_id' => $internacao->getId(),
                'medicamento' => $medicamento
            ];

            return $this->perguntarDoseMedicamento($dados, $session);

        } catch (\Exception $e) {
            return ['message' => "❌ Erro: " . $e->getMessage()];
        }
    }

    private function perguntarDoseMedicamento(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'dose_medicamento',
            'dados' => $dados
        ]);

        return [
            'message' => "💊 Prescrição de **{$dados['medicamento']}** para **{$dados['pet_nome']}**\n\n💉 Qual a dose?\n\nExemplo: 1 comprimido, 5ml, 2 gotas",
            'aguardando' => true
        ];
    }

    private function perguntarFrequenciaMedicamento(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'frequencia_medicamento',
            'dados' => $dados
        ]);

        return [
            'message' => "⏰ Qual a frequência?\n\nExemplo: 8 em 8 horas, 12 em 12 horas, 6 em 6 horas",
            'aguardando' => true
        ];
    }

    private function perguntarDuracaoMedicamento(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'duracao_medicamento',
            'dados' => $dados
        ]);

        return [
            'message' => "📅 Por quantos dias?\n\nExemplo: 7 dias, 10 dias, 5 dias",
            'aguardando' => true
        ];
    }

    private function perguntarViaMedicamento(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando_resposta' => true,
            'etapa' => 'via_medicamento',
            'dados' => $dados
        ]);

        return [
            'message' => "💉 Qual a via de administração?\n\n• Oral\n• Intravenosa (IV)\n• Intramuscular (IM)\n• Subcutânea (SC)\n• Tópica",
            'aguardando' => true
        ];
    }

    private function finalizarPrescricao(array $dados, int $baseId, $session): array
    {
        try {
            // Buscar ou criar medicamento
            $medicamentoObj = $this->ems->getRepository(\App\Entity\Medicamento::class)
                ->createQueryBuilder('m')
                ->where('LOWER(m.nome) = :nome')
                ->setParameter('nome', strtolower($dados['medicamento']))
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$medicamentoObj) {
                $medicamentoObj = new \App\Entity\Medicamento();
                $medicamentoObj->setNome($dados['medicamento']);
                if (isset($dados['via'])) {
                    $medicamentoObj->setVia($dados['via']);
                }
                $this->ems->persist($medicamentoObj);
                $this->ems->flush();
            }

            // Criar prescrição
            $prescricao = new \App\Entity\InternacaoPrescricao();
            $prescricao->setInternacaoId($dados['internacao_id']);
            $prescricao->setMedicamento($medicamentoObj);
            $prescricao->setDescricao($dados['medicamento']);
            $prescricao->setDose($dados['dose']);
            $prescricao->setFrequencia($dados['frequencia']);
            $prescricao->setFrequenciaHoras($dados['frequencia_horas']);
            $prescricao->setDuracaoDias($dados['duracao_dias']);
            $prescricao->setDataHora(new \DateTime());
            $prescricao->setCriadoEm(new \DateTime());

            $this->ems->persist($prescricao);
            $this->ems->flush();

            // Criar eventos no calendário
            $internacaoRepo = $this->ems->getRepository(\App\Entity\Internacao::class);
            $dataInicio = new \DateTime();
            $totalEventos = 0;

            // Calcular quantas aplicações por dia (arredonda para cima)
            $aplicacoesPorDia = (int)ceil(24 / $dados['frequencia_horas']);

            for ($dia = 0; $dia < $dados['duracao_dias']; $dia++) {
                for ($aplicacao = 0; $aplicacao < $aplicacoesPorDia; $aplicacao++) {
                    $dataEvento = clone $dataInicio;
                    $dataEvento->modify("+{$dia} days");

                    // Calcula o horário da aplicação
                    $horasAdicionar = $aplicacao * $dados['frequencia_horas'];

                    // Se ultrapassar 24h, pula para o próximo dia
                    if ($horasAdicionar >= 24) {
                        continue;
                    }

                    $dataEvento->modify("+{$horasAdicionar} hours");

                    $titulo = "{$dados['medicamento']} - {$dados['dose']}";
                    $descricao = "Via: " . ($dados['via'] ?? 'Oral') . " | Frequência: {$dados['frequencia']}";

                    $internacaoRepo->inserirEvento(
                        $baseId,
                        $dados['internacao_id'],
                        $dados['pet_id'],
                        'prescricao',
                        $titulo,
                        $descricao,
                        $dataEvento,
                        'pendente'
                    );

                    $totalEventos++;
                }
            }

            $session->remove('ia_contexto');

            $message = "✅ Prescrição criada com sucesso!\n\n";
            $message .= "🐕 Pet: {$dados['pet_nome']}\n";
            $message .= "💊 Medicamento: {$dados['medicamento']}\n";
            $message .= "💉 Dose: {$dados['dose']}\n";
            $message .= "⏰ Frequência: {$dados['frequencia']}\n";
            $message .= "📅 Duração: {$dados['duracao_dias']} dias\n";
            if (isset($dados['via'])) {
                $message .= "💉 Via: {$dados['via']}\n";
            }
            $message .= "📋 ID Prescrição: #{$prescricao->getId()}\n";
            $message .= "📆 {$totalEventos} eventos criados no calendário!";

            return [
                'message' => $message,
                'acao' => 'prescrever',
                'dados' => ['prescricao_id' => $prescricao->getId()]
            ];

        } catch (\Exception $e) {
            $session->remove('ia_contexto');
            return ['message' => "❌ Erro ao criar prescrição: " . $e->getMessage()];
        }
    }

    private function aplicarVacina(array $analise, int $baseId): array
    {
        $entidades = $analise['entidades'];
        $nomeBusca = $entidades['nome'] ?? null;
        $comando = $analise['comando_original'];

        if (!$nomeBusca) {
            return ['message' => '❌ Especifique o nome do pet.\nExemplo: "vacinar Max contra raiva"'];
        }

        try {
            // Buscar pet
            $pet = $this->ems->getRepository(\App\Entity\Pet::class)
                ->createQueryBuilder('p')
                ->where('LOWER(p.nome) LIKE :nome')
                ->andWhere('p.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$pet) {
                return ['message' => "❌ Pet '{$nomeBusca}' não encontrado."];
            }

            // Extrair tipo de vacina
            $tipoVacina = 'V10'; // padrão
            if (stripos($comando, 'raiva') !== false) $tipoVacina = 'Raiva';
            elseif (stripos($comando, 'v10') !== false) $tipoVacina = 'V10';
            elseif (stripos($comando, 'v8') !== false) $tipoVacina = 'V8';
            elseif (stripos($comando, 'giárdia') !== false || stripos($comando, 'giardia') !== false) $tipoVacina = 'Giárdia';

            // Criar vacina
            $vacina = new \App\Entity\Vacina();
            $vacina->setPetId($pet->getId());
            $vacina->setTipo($tipoVacina);
            $vacina->setDataAplicacao(new \DateTime());
            $vacina->setEstabelecimentoId($baseId);

            $this->ems->persist($vacina);
            $this->ems->flush();

            $message = "💉 Vacina aplicada!\n\n";
            $message .= "🐕 Pet: {$pet->getNome()}\n";
            $message .= "💊 Vacina: {$tipoVacina}\n";
            $message .= "📋 ID: #{$vacina->getId()}\n";
            $message .= "📅 Data: " . $vacina->getDataAplicacao()->format('d/m/Y H:i') . "\n";

            return ['message' => $message];

        } catch (\Exception $e) {
            return ['message' => "❌ Erro: " . $e->getMessage()];
        }
    }

    private function registrarObito(array $analise, int $baseId): array
    {
        $entidades = $analise['entidades'];
        $nomeBusca = $entidades['nome'] ?? null;

        if (!$nomeBusca) {
            return ['message' => '❌ Especifique o nome do pet.\nExemplo: "registrar óbito do Luna"'];
        }

        try {
            // Buscar pet
            $pet = $this->ems->getRepository(\App\Entity\Pet::class)
                ->createQueryBuilder('p')
                ->where('LOWER(p.nome) LIKE :nome')
                ->andWhere('p.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$pet) {
                return ['message' => "❌ Pet '{$nomeBusca}' não encontrado."];
            }

            // Verificar se há internação ativa e finalizar
            $internacao = $this->ems->getRepository(\App\Entity\Internacao::class)
                ->createQueryBuilder('i')
                ->where('i.pet_id = :petId')
                ->andWhere('i.estabelecimento_id = :estab')
                ->andWhere('i.status = :status')
                ->setParameter('petId', $pet->getId())
                ->setParameter('estab', $baseId)
                ->setParameter('status', 'ativo')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($internacao) {
                $internacao->setStatus('obito');
                $this->ems->flush();
            }

            $message = "💔 Óbito registrado.\n\n";
            $message .= "🐕 Pet: {$pet->getNome()}\n";
            $message .= "📅 Data: " . (new \DateTime())->format('d/m/Y H:i') . "\n";
            $message .= "\nNossos sentimentos à família.";

            return ['message' => $message];

        } catch (\Exception $e) {
            return ['message' => "❌ Erro: " . $e->getMessage()];
        }
    }

    private function criarOrcamento(array $analise, int $baseId): array
    {
        return [
            'message' => '💰 Para criar orçamento, acesse:\nMenu → Orçamentos → Novo Orçamento'
        ];
    }

    private function registrarVenda(array $analise, int $baseId): array
    {
        return [
            'message' => '🛒 Para registrar venda, acesse:\nMenu → PDV (Ponto de Venda)'
        ];
    }

    private function buscarInformacao(array $analise, int $baseId): array
    {
        $entidades = $analise['entidades'];
        $nomeBusca = $entidades['nome'] ?? null;

        if (!$nomeBusca) {
            return [
                'message' => '❌ Por favor, especifique o que deseja buscar.\nExemplo: "buscar cliente Maria" ou "mostrar pets do João"'
            ];
        }

        try {
            // Buscar clientes
            $clientes = $this->ems->getRepository(\App\Entity\Cliente::class)
                ->createQueryBuilder('c')
                ->where('LOWER(c.nome) LIKE :nome')
                ->andWhere('c.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            // Buscar pets
            $pets = $this->ems->getRepository(\App\Entity\Pet::class)
                ->createQueryBuilder('p')
                ->where('LOWER(p.nome) LIKE :nome')
                ->andWhere('p.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            $message = "🔍 Resultados da busca por '{$nomeBusca}':\n\n";

            if (count($clientes) > 0) {
                $message .= "👥 **Clientes encontrados:**\n";
                foreach ($clientes as $cliente) {
                    $message .= "• {$cliente->getNome()}";
                    if ($cliente->getTelefone()) {
                        $message .= " - Tel: {$cliente->getTelefone()}";
                    }
                    $message .= "\n";
                }
                $message .= "\n";
            }

            if (count($pets) > 0) {
                $message .= "🐕 **Pets encontrados:**\n";
                foreach ($pets as $pet) {
                    $message .= "• {$pet->getNome()}";
                    if ($pet->getEspecie()) {
                        $message .= " ({$pet->getEspecie()}";
                        if ($pet->getRaca()) {
                            $message .= " - {$pet->getRaca()}";
                        }
                        $message .= ")";
                    }
                    $message .= "\n";
                }
            }

            if (count($clientes) === 0 && count($pets) === 0) {
                $message .= "❌ Nenhum resultado encontrado.";
            }

            return [
                'message' => $message,
                'dados' => [
                    'clientes' => count($clientes),
                    'pets' => count($pets)
                ]
            ];

        } catch (\Exception $e) {
            return [
                'message' => "❌ Erro ao buscar: " . $e->getMessage()
            ];
        }
    }

    private function consultarDebitos(array $analise, int $baseId): array
    {
        $entidades = $analise['entidades'];
        $nomeBusca = $entidades['nome'] ?? null;

        if (!$nomeBusca) {
            return ['message' => '❌ Especifique o nome do cliente.\nExemplo: "débitos do João" ou "quanto Maria deve"'];
        }

        try {
            // Buscar cliente
            $cliente = $this->ems->getRepository(\App\Entity\Cliente::class)
                ->createQueryBuilder('c')
                ->where('LOWER(c.nome) LIKE :nome')
                ->andWhere('c.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$cliente) {
                return ['message' => "❌ Cliente '{$nomeBusca}' não encontrado."];
            }

            // Buscar débitos pendentes
            $debitos = $this->ems->getRepository(\App\Entity\FinanceiroPendente::class)
                ->createQueryBuilder('f')
                ->where('f.clienteId = :clienteId')
                ->andWhere('f.estabelecimentoId = :estab')
                ->andWhere('f.status = :status')
                ->setParameter('clienteId', $cliente->getId())
                ->setParameter('estab', $baseId)
                ->setParameter('status', 'Pendente')
                ->getQuery()
                ->getResult();

            $totalDebito = 0;
            $message = "💰 Débitos de **{$cliente->getNome()}**\n\n";

            if (count($debitos) > 0) {
                foreach ($debitos as $debito) {
                    $totalDebito += $debito->getValor();
                    $message .= "• {$debito->getDescricao()}: R$ " . number_format($debito->getValor(), 2, ',', '.') . "\n";
                    $message .= "  Data: " . $debito->getData()->format('d/m/Y') . "\n\n";
                }
                $message .= "**Total devido: R$ " . number_format($totalDebito, 2, ',', '.') . "**";
            } else {
                $message .= "✅ Nenhum débito pendente!\nCliente está em dia. 🎉";
            }

            return ['message' => $message];

        } catch (\Exception $e) {
            return ['message' => "❌ Erro: " . $e->getMessage()];
        }
    }

    private function consultarHistorico(array $analise, int $baseId): array
    {
        $entidades = $analise['entidades'];
        $nomeBusca = $entidades['nome'] ?? null;

        if (!$nomeBusca) {
            return ['message' => '❌ Especifique o nome do pet.\nExemplo: "histórico do Rex" ou "ficha da Luna"'];
        }

        try {
            // Buscar pet
            $pet = $this->ems->getRepository(\App\Entity\Pet::class)
                ->createQueryBuilder('p')
                ->where('LOWER(p.nome) LIKE :nome')
                ->andWhere('p.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$pet) {
                return ['message' => "❌ Pet '{$nomeBusca}' não encontrado."];
            }

            $message = "📋 Histórico de **{$pet->getNome()}**\n\n";

            // Informações básicas
            $message .= "🐕 **Dados:**\n";
            if ($pet->getEspecie()) $message .= "• Espécie: {$pet->getEspecie()}\n";
            if ($pet->getRaca()) $message .= "• Raça: {$pet->getRaca()}\n";
            if ($pet->getIdade()) $message .= "• Idade: {$pet->getIdade()} anos\n";
            if ($pet->getPeso()) $message .= "• Peso: {$pet->getPeso()} kg\n";
            $message .= "\n";

            // Vacinas
            $vacinas = $this->ems->getRepository(\App\Entity\Vacina::class)
                ->createQueryBuilder('v')
                ->where('v.petId = :petId')
                ->andWhere('v.estabelecimentoId = :estab')
                ->setParameter('petId', $pet->getId())
                ->setParameter('estab', $baseId)
                ->orderBy('v.dataAplicacao', 'DESC')
                ->setMaxResults(3)
                ->getQuery()
                ->getResult();

            if (count($vacinas) > 0) {
                $message .= "💉 **Últimas Vacinas:**\n";
                foreach ($vacinas as $vacina) {
                    $message .= "• {$vacina->getTipo()} - " . $vacina->getDataAplicacao()->format('d/m/Y') . "\n";
                }
                $message .= "\n";
            }

            // Internações
            $internacoes = $this->ems->getRepository(\App\Entity\Internacao::class)
                ->createQueryBuilder('i')
                ->where('i.pet_id = :petId')
                ->andWhere('i.estabelecimento_id = :estab')
                ->setParameter('petId', $pet->getId())
                ->setParameter('estab', $baseId)
                ->orderBy('i.data_inicio', 'DESC')
                ->setMaxResults(2)
                ->getQuery()
                ->getResult();

            if (count($internacoes) > 0) {
                $message .= "🏥 **Internações:**\n";
                foreach ($internacoes as $int) {
                    $status = $int->getStatus() === 'ativa' ? '🔴 Ativa' : '✅ Finalizada';
                    $message .= "• {$status} - " . $int->getDataInicio()->format('d/m/Y') . "\n";
                    if ($int->getMotivo()) $message .= "  Motivo: {$int->getMotivo()}\n";
                }
                $message .= "\n";
            }

            $message .= "🔗 Acesse a ficha completa em:\n/clinica/pet/{$pet->getId()}";

            return ['message' => $message];

        } catch (\Exception $e) {
            return ['message' => "❌ Erro: " . $e->getMessage()];
        }
    }

    private function gerarRelatorio(array $analise, int $baseId): array
    {
        $comando = $analise['comando_original'];

        try {
            $hoje = new \DateTime();
            $message = "📊 **Relatório do Sistema**\n";
            $message .= "Data: " . $hoje->format('d/m/Y H:i') . "\n\n";

            // Pets cadastrados
            $totalPets = $this->ems->getRepository(\App\Entity\Pet::class)
                ->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->where('p.estabelecimentoId = :estab')
                ->setParameter('estab', $baseId)
                ->getQuery()
                ->getSingleScalarResult();

            $message .= "🐕 **Pets:** {$totalPets} cadastrados\n";

            // Clientes
            $totalClientes = $this->ems->getRepository(\App\Entity\Cliente::class)
                ->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->where('c.estabelecimentoId = :estab')
                ->setParameter('estab', $baseId)
                ->getQuery()
                ->getSingleScalarResult();

            $message .= "👥 **Clientes:** {$totalClientes} cadastrados\n\n";

            // Internações ativas
            $internacoes = $this->ems->getRepository(\App\Entity\Internacao::class)
                ->createQueryBuilder('i')
                ->select('COUNT(i.id)')
                ->where('i.estabelecimento_id = :estab')
                ->andWhere('i.status = :status')
                ->setParameter('estab', $baseId)
                ->setParameter('status', 'ativa')
                ->getQuery()
                ->getSingleScalarResult();

            $message .= "🏥 **Internações Ativas:** {$internacoes}\n";

            // Agendamentos hoje
            $inicioHoje = (clone $hoje)->setTime(0, 0, 0);
            $fimHoje = (clone $hoje)->setTime(23, 59, 59);

            $agendamentosHoje = $this->ems->getRepository(\App\Entity\Agendamento::class)
                ->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->where('a.estabelecimentoId = :estab')
                ->andWhere('a.data BETWEEN :inicio AND :fim')
                ->setParameter('estab', $baseId)
                ->setParameter('inicio', $inicioHoje)
                ->setParameter('fim', $fimHoje)
                ->getQuery()
                ->getSingleScalarResult();

            $message .= "📅 **Agendamentos Hoje:** {$agendamentosHoje}\n\n";

            // Financeiro do mês
            $inicioMes = (clone $hoje)->modify('first day of this month')->setTime(0, 0, 0);

            $entradas = $this->ems->getRepository(\App\Entity\Financeiro::class)
                ->createQueryBuilder('f')
                ->select('SUM(f.valor)')
                ->where('f.estabelecimentoId = :estab')
                ->andWhere('f.tipo = :tipo')
                ->andWhere('f.data >= :inicio')
                ->setParameter('estab', $baseId)
                ->setParameter('tipo', 'ENTRADA')
                ->setParameter('inicio', $inicioMes)
                ->getQuery()
                ->getSingleScalarResult();

            $message .= "💰 **Faturamento do Mês:**\n";
            $message .= "R$ " . number_format($entradas ?? 0, 2, ',', '.') . "\n\n";

            // Débitos pendentes
            $debitos = $this->ems->getRepository(\App\Entity\FinanceiroPendente::class)
                ->createQueryBuilder('f')
                ->select('SUM(f.valor)')
                ->where('f.estabelecimentoId = :estab')
                ->andWhere('f.status = :status')
                ->setParameter('estab', $baseId)
                ->setParameter('status', 'Pendente')
                ->getQuery()
                ->getSingleScalarResult();

            $message .= "⚠️ **Débitos Pendentes:**\n";
            $message .= "R$ " . number_format($debitos ?? 0, 2, ',', '.') . "\n";

            return ['message' => $message, 'acao' => 'relatorio'];

        } catch (\Exception $e) {
            return ['message' => "❌ Erro ao gerar relatório: " . $e->getMessage()];
        }
    }

    // ========== ATENDIMENTO/CONSULTA ==========

    private function registrarAtendimento(array $analise, int $baseId, $session): array
    {
        try {
            error_log("IA: Iniciando registrarAtendimento");
            error_log("IA: Analise = " . json_encode($analise));

            $petNome = $analise['entidades']['nome'] ?? $analise['pet'] ?? null;
            error_log("IA: Pet nome = " . $petNome);

            if (!$petNome) {
                error_log("IA: Pet nome não identificado, perguntando");
                // Se não identificou o pet, pergunta qual é
                $session->set('ia_contexto', [
                    'aguardando' => 'nome_pet_atendimento',
                    'dados' => []
                ]);

                return [
                    'message' => '🩺 **Atendimento Veterinário**\n\n🐕 Qual o nome do pet?',
                    'aguardando' => true
                ];
            }

            error_log("IA: Buscando pet no banco");
            // Busca flexível do pet (tolera erros de digitação e maiúsculas/minúsculas)
            $pet = $this->buscarPetFlexivel($petNome, $baseId);

            error_log("IA: Pet encontrado = " . ($pet ? 'SIM (ID: ' . $pet->getId() . ')' : 'NÃO'));

            if (!$pet) {
                return [
                    'message' => "❌ Pet **{$petNome}** não encontrado no sistema.\n\n💡 Verifique se o nome está correto ou cadastre o pet primeiro.",
                    'acao' => 'erro'
                ];
            }

            error_log("IA: Verificando dono_id = " . $pet->getDono_Id());

            // Verificar se tem dono
            if (!$pet->getDono_Id()) {
                return [
                    'message' => "⚠️ O pet **{$pet->getNome()}** não tem um tutor cadastrado.\n\nPor favor, vincule um tutor ao pet primeiro.",
                    'acao' => 'erro'
                ];
            }

            error_log("IA: Preparando dados do fluxo");

            // Iniciar fluxo
            $dados = [
                'pet_id' => $pet->getId(),
                'pet_nome' => $pet->getNome(),
                'cliente_id' => $pet->getDono_Id(),
            ];

            error_log("IA: Salvando contexto na sessão");

            $session->set('ia_contexto', [
                'aguardando' => 'tipo_atendimento',
                'dados' => $dados
            ]);

            error_log("IA: Retornando mensagem de sucesso");

            return [
                'message' => "🩺 Atendimento para **{$pet->getNome()}**\n\n📋 Qual o tipo de atendimento?\n\nExemplo: Consulta, Retorno, Emergência, Check-up",
                'aguardando' => true
            ];

        } catch (\Exception $e) {
            error_log("IA: ERRO CAPTURADO = " . $e->getMessage());
            error_log("IA: STACK TRACE = " . $e->getTraceAsString());
            return [
                'message' => "❌ Erro ao iniciar atendimento: " . $e->getMessage(),
                'acao' => 'erro'
            ];
        }
    }

    private function perguntarObservacoesAtendimento(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando' => 'observacoes_atendimento',
            'dados' => $dados
        ]);

        return [
            'message' => "📝 Observações do atendimento?\n\nDescreva brevemente o motivo da consulta ou principais queixas.",
            'aguardando' => true
        ];
    }

    private function perguntarAnamneseAtendimento(array $dados, $session): array
    {
        $session->set('ia_contexto', [
            'aguardando' => 'anamnese_atendimento',
            'dados' => $dados
        ]);

        return [
            'message' => "📋 Anamnese completa?\n\nDescreva os detalhes do atendimento, exame físico, diagnóstico, etc.\n\n(Ou digite 'pular' para deixar em branco)",
            'aguardando' => true
        ];
    }

    private function finalizarAtendimento(array $dados, int $baseId, $session): array
    {
        try {
            $consulta = new \App\Entity\Consulta();
            $consulta->setEstabelecimentoId($baseId);
            $consulta->setClienteId($dados['cliente_id']);
            $consulta->setPetId($dados['pet_id']);
            $consulta->setData(new \DateTime());
            $consulta->setHora(new \DateTime());
            $consulta->setTipo($dados['tipo_atendimento']);
            $consulta->setObservacoes($dados['observacoes_atendimento']);
            $consulta->setStatus('atendido');
            $consulta->setCriadoEm(new \DateTime());

            // Anamnese em formato Delta (Quill)
            if (!empty($dados['anamnese']) && strtolower($dados['anamnese']) !== 'pular') {
                $anamnese = json_encode([
                    'ops' => [
                        ['insert' => $dados['anamnese']]
                    ]
                ]);
                $consulta->setAnamnese($anamnese);
            }

            // Salvar usando o repositório
            $consultaRepo = $this->ems->getRepository(\App\Entity\Consulta::class);
            if (method_exists($consultaRepo, 'salvarConsulta')) {
                $consultaRepo->salvarConsulta($consulta);
            } else {
                $this->ems->persist($consulta);
                $this->ems->flush();
            }

            $session->remove('ia_contexto');

            $message = "✅ Atendimento registrado com sucesso!\n\n";
            $message .= "🐕 Pet: {$dados['pet_nome']}\n";
            $message .= "📋 Tipo: {$dados['tipo_atendimento']}\n";
            $message .= "📝 Observações: {$dados['observacoes_atendimento']}\n";
            $message .= "🕐 Data/Hora: " . date('d/m/Y H:i') . "\n\n";
            $message .= "📂 O atendimento foi adicionado à ficha do pet!";

            return [
                'message' => $message,
                'acao' => 'atendimento',
                'dados' => ['consulta_id' => $consulta->getId(), 'pet_id' => $dados['pet_id']]
            ];

        } catch (\Exception $e) {
            $session->remove('ia_contexto');
            return ['message' => "❌ Erro ao registrar atendimento: " . $e->getMessage()];
        }
    }

    private function mostrarAjuda(): array
    {
        $message = "🤖 **Dra. HomePet - Assistente IA**\n\n";
        $message .= "Posso ajudar você com:\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $message .= "📋 **CADASTROS**\n";
        $message .= "• cadastrar pet Cacal do tutor Lulu\n";
        $message .= "• novo cliente João Silva\n\n";

        $message .= "📅 **AGENDAMENTOS**\n";
        $message .= "• agendar banho para Rex amanhã às 14h\n";
        $message .= "• marcar consulta para Luna hoje\n\n";

        $message .= "🏥 **CLÍNICA**\n";
        $message .= "• internar Harry por pneumonia\n";
        $message .= "• prescrever dipirona para Luna\n";
        $message .= "• atender Rex\n";
        $message .= "• consulta para Luna\n";
        $message .= "• vacinar Max contra raiva\n";
        $message .= "• dar alta para Rex\n\n";

        $message .= "🔍 **CONSULTAS**\n";
        $message .= "• buscar cliente Maria\n";
        $message .= "• débitos do João\n";
        $message .= "• histórico do Rex\n";
        $message .= "• ficha da Luna\n\n";

        $message .= "📊 **RELATÓRIOS**\n";
        $message .= "• relatório do sistema\n";
        $message .= "• resumo do mês\n\n";

        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "💡 **Dica:** Fale naturalmente, eu entendo! 😊";

        return ['message' => $message, 'acao' => 'ajuda'];
    }

    // ========== MÉTODOS AUXILIARES DE NORMALIZAÇÃO ==========

    private function normalizarTexto(string $texto): string
    {
        // Remove acentos
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        // Remove caracteres especiais, mantém apenas letras, números e espaços
        $texto = preg_replace('/[^a-z0-9\s]/i', '', $texto);
        // Remove espaços extras
        $texto = preg_replace('/\s+/', ' ', trim($texto));
        return $texto;
    }

    private function corrigirErrosDigitacao(string $comando): string
    {
        $palavrasCorretas = [
            'consulta', 'atendimento', 'atender', 'prescrever', 'prescricao',
            'internar', 'internacao', 'agendar', 'marcar', 'vacinar', 'vacina',
            'cadastrar', 'registrar', 'buscar', 'procurar', 'debito', 'divida',
            'historico', 'ficha', 'alta', 'obito', 'orcamento', 'venda'
        ];

        $palavras = explode(' ', $comando);
        $palavrasCorrigidas = [];

        foreach ($palavras as $palavra) {
            $melhorMatch = $palavra;
            $menorDistancia = 3; // Máximo de 3 caracteres de diferença

            foreach ($palavrasCorretas as $correta) {
                $distancia = levenshtein($palavra, $correta);
                if ($distancia < $menorDistancia) {
                    $menorDistancia = $distancia;
                    $melhorMatch = $correta;
                }
            }

            $palavrasCorrigidas[] = $melhorMatch;
        }

        return implode(' ', $palavrasCorrigidas);
    }

    private function buscarPetFlexivel(string $petNome, int $baseId)
    {
        // 1. Busca exata (case-insensitive)
        $pet = $this->ems->getRepository(\App\Entity\Pet::class)
            ->createQueryBuilder('p')
            ->where('LOWER(p.nome) = :nome')
            ->andWhere('p.estabelecimentoId = :baseId')
            ->setParameter('nome', strtolower($petNome))
            ->setParameter('baseId', $baseId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($pet) return $pet;

        // 2. Busca com LIKE (contém)
        $pet = $this->ems->getRepository(\App\Entity\Pet::class)
            ->createQueryBuilder('p')
            ->where('LOWER(p.nome) LIKE :nome')
            ->andWhere('p.estabelecimentoId = :baseId')
            ->setParameter('nome', '%' . strtolower($petNome) . '%')
            ->setParameter('baseId', $baseId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($pet) return $pet;

        // 3. Busca aproximada (Levenshtein)
        $todosPets = $this->ems->getRepository(\App\Entity\Pet::class)
            ->createQueryBuilder('p')
            ->where('p.estabelecimentoId = :baseId')
            ->setParameter('baseId', $baseId)
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        $melhorMatch = null;
        $menorDistancia = 3; // Máximo de 3 caracteres de diferença

        foreach ($todosPets as $p) {
            $distancia = levenshtein(strtolower($petNome), strtolower($p->getNome()));
            if ($distancia < $menorDistancia) {
                $menorDistancia = $distancia;
                $melhorMatch = $p;
            }
        }

        return $melhorMatch;
    }

    private function registrarLog(string $comando, array $analise, array $resultado, int $baseId): void
    {
        $log = sprintf(
            "[%s] Base: %d | Comando: %s | Ação: %s | Resultado: %s\n",
            date('Y-m-d H:i:s'),
            $baseId,
            $comando,
            $analise['acao'],
            substr($resultado['message'], 0, 100)
        );

        file_put_contents(
            __DIR__ . '/../../var/log/ia_assistente_' . date('Ymd') . '.log',
            $log,
            FILE_APPEND
        );
    }
}
