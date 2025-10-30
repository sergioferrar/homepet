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
 * @Route("/ia/assistente")
 */
class IaAssistenteController extends DefaultController
{
    private $em;

    public function __construct(
        ?Security $security,
        ManagerRegistry $managerRegistry,
        RequestStack $request,
        TempDirManager $tempDirManager,
        DatabaseBkp $databaseBkp,
        EntityManagerInterface $em
    ) {
        parent::__construct($security, $managerRegistry, $request, $tempDirManager, $databaseBkp);
        $this->em = $em;
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
            
            if ($contexto && isset($contexto['aguardando_resposta'])) {
                // Processar resposta do usuário
                $resultado = $this->processarResposta($comando, $contexto, $baseId, $session);
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
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function processarResposta(string $resposta, array $contexto, int $baseId, $session): array
    {
        $etapa = $contexto['etapa'] ?? '';
        $dados = $contexto['dados'] ?? [];
        
        switch ($etapa) {
            case 'confirmar_pet':
                // Usuário confirmou o pet
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
                
            default:
                $session->remove('ia_contexto');
                return ['message' => '❌ Erro no fluxo de agendamento.'];
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
            $pet = $this->em->getRepository(\App\Entity\Pet::class)->find($dados['pet_id']);
            
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
            
            $this->em->persist($agendamento);
            $this->em->flush();
            
            // Criar relação com serviço
            if (isset($dados['servico_id'])) {
                $agendamentoPetServico = new \App\Entity\AgendamentoPetServico();
                $agendamentoPetServico->setAgendamentoId($agendamento->getId());
                $agendamentoPetServico->setPetId($pet->getId());
                $agendamentoPetServico->setServicoId($dados['servico_id']);
                $agendamentoPetServico->setEstabelecimentoId($baseId);
                
                $this->em->persist($agendamentoPetServico);
                $this->em->flush();
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
        $comando = strtolower($comando);
        
        // Detectar tipo de ação
        $acoes = [
            'cadastrar' => ['cadastrar', 'cadastra', 'criar', 'adicionar', 'registrar'],
            'agendar' => ['agendar', 'marcar', 'reservar'],
            'internar' => ['internar', 'internação', 'internacao', 'hospitalizar'],
            'alta' => ['alta', 'liberar', 'liberação', 'liberacao', 'dar alta'],
            'prescrever' => ['prescrever', 'prescrição', 'prescricao', 'receitar', 'medicar'],
            'vacinar' => ['vacinar', 'vacina', 'vacinação', 'vacinacao', 'aplicar vacina'],
            'obito' => ['óbito', 'obito', 'faleceu', 'morreu', 'morte'],
            'orcamento' => ['orçamento', 'orcamento', 'cotação', 'cotacao', 'preço', 'preco'],
            'venda' => ['vender', 'venda', 'comprar', 'pdv'],
            'consulta' => ['consultar', 'consulta', 'atender', 'atendimento'],
            'buscar' => ['buscar', 'procurar', 'encontrar', 'listar', 'mostrar']
        ];

        $acaoDetectada = 'desconhecida';
        foreach ($acoes as $acao => $palavras) {
            foreach ($palavras as $palavra) {
                if (strpos($comando, $palavra) !== false) {
                    $acaoDetectada = $acao;
                    break 2;
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

        // Extrair nomes - mais flexível
        // Tenta "para o/a Nome" ou "pro/pra Nome"
        if (preg_match('/(?:para|pro|pra)\s+(?:o|a)?\s*([A-Z][a-zà-úA-ZÀ-Ú]+)/i', $comando, $matches)) {
            $nome = trim($matches[1]);
            // Remove palavras de tempo que podem ter sido capturadas
            $palavrasTempo = ['hoje', 'amanha', 'amanhã', 'ontem', 'dia'];
            $nomePartes = explode(' ', strtolower($nome));
            if (!in_array($nomePartes[0], $palavrasTempo)) {
                $entidades['nome'] = $nome;
            }
        }
        
        // Tenta "do/da Nome"
        if (!isset($entidades['nome']) && preg_match('/(?:do|da)\s+([A-Z][a-zà-úA-ZÀ-Ú]+)/i', $comando, $matches)) {
            $nome = trim($matches[1]);
            $palavrasTempo = ['hoje', 'amanha', 'amanhã', 'ontem', 'dia'];
            $nomePartes = explode(' ', strtolower($nome));
            if (!in_array($nomePartes[0], $palavrasTempo)) {
                $entidades['nome'] = $nome;
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
                return $this->internarPet($analise, $baseId);
            
            case 'alta':
                return $this->darAlta($analise, $baseId);
            
            case 'prescrever':
                return $this->prescreverMedicamento($analise, $baseId);
            
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
            
            default:
                return [
                    'message' => 'Desculpe, não entendi o comando.\n\n📋 Exemplos:\n• "agendar banho para Rex amanhã às 14h"\n• "cadastrar pet Cacal do tutor Lulu"\n• "internar Rex por pneumonia"\n• "prescrever dipirona para Luna"\n• "vacinar Max contra raiva"\n• "dar alta para Rex"\n• "registrar óbito do Luna"\n• "buscar cliente Maria"'
                ];
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
            $clienteExistente = $this->em->getRepository(\App\Entity\Cliente::class)
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
                
                $this->em->persist($cliente);
                $this->em->flush();
                
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
                
                $this->em->persist($pet);
                $this->em->flush();
                
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
            $pet = $this->em->getRepository(\App\Entity\Pet::class)
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
                $cliente = $this->em->getRepository(\App\Entity\Cliente::class)
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
                    $pets = $this->em->getRepository(\App\Entity\Pet::class)
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
            $servicoObj = $this->em->getRepository(\App\Entity\Servico::class)
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
            $pet = $this->em->getRepository(\App\Entity\Pet::class)
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
                $cliente = $this->em->getRepository(\App\Entity\Cliente::class)
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
                    $sugestoes = $this->em->getRepository(\App\Entity\Pet::class)
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
            $servicoObj = $this->em->getRepository(\App\Entity\Servico::class)
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
            
            $this->em->persist($agendamento);
            
            // Flush para obter o ID do agendamento
            $this->em->flush();
            
            // Criar relação com serviço se encontrado
            if ($servicoObj && $pet) {
                $agendamentoPetServico = new \App\Entity\AgendamentoPetServico();
                $agendamentoPetServico->setAgendamentoId($agendamento->getId());
                $agendamentoPetServico->setPetId($pet->getId());
                $agendamentoPetServico->setServicoId($servicoObj->getId());
                $agendamentoPetServico->setEstabelecimentoId($baseId);
                
                $this->em->persist($agendamentoPetServico);
            }
            
            $this->em->flush();
            
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

    private function internarPet(array $analise, int $baseId): array
    {
        $entidades = $analise['entidades'];
        $nomeBusca = $entidades['nome'] ?? null;
        $comando = $analise['comando_original'];
        
        if (!$nomeBusca) {
            return ['message' => '❌ Especifique o nome do pet.\nExemplo: "internar Rex por pneumonia"'];
        }
        
        try {
            // Buscar pet
            $pet = $this->em->getRepository(\App\Entity\Pet::class)
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
            
            // Extrair motivo
            $motivo = '';
            if (preg_match('/por\s+(.+?)(?:\.|$)/i', $comando, $matches)) {
                $motivo = trim($matches[1]);
            }
            
            // Criar internação
            $internacao = new \App\Entity\Internacao();
            $internacao->setPetId($pet->getId());
            $internacao->setDonoId($pet->getDono_Id());
            $internacao->setEstabelecimentoId($baseId);
            $internacao->setDataInicio(new \DateTime());
            $internacao->setStatus('ativo');
            $internacao->setSituacao('estável');
            
            if ($motivo) {
                $internacao->setMotivo($motivo);
                $internacao->setDiagnostico($motivo);
            }
            
            $this->em->persist($internacao);
            $this->em->flush();
            
            $message = "🏥 Internação registrada!\n\n";
            $message .= "🐕 Pet: {$pet->getNome()}\n";
            $message .= "📋 ID Internação: #{$internacao->getId()}\n";
            $message .= "📅 Data: " . $internacao->getDataInicio()->format('d/m/Y H:i') . "\n";
            if ($motivo) {
                $message .= "🩺 Motivo: {$motivo}\n";
            }
            $message .= "📊 Status: Ativo\n";
            
            return ['message' => $message, 'dados' => ['internacao_id' => $internacao->getId()]];
            
        } catch (\Exception $e) {
            return ['message' => "❌ Erro: " . $e->getMessage()];
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
            $pet = $this->em->getRepository(\App\Entity\Pet::class)
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
            $internacao = $this->em->getRepository(\App\Entity\Internacao::class)
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
            $this->em->flush();
            
            $message = "✅ Alta médica registrada!\n\n";
            $message .= "🐕 Pet: {$pet->getNome()}\n";
            $message .= "📋 ID Internação: #{$internacao->getId()}\n";
            $message .= "📅 Alta em: " . (new \DateTime())->format('d/m/Y H:i') . "\n";
            
            return ['message' => $message];
            
        } catch (\Exception $e) {
            return ['message' => "❌ Erro: " . $e->getMessage()];
        }
    }
    
    private function prescreverMedicamento(array $analise, int $baseId): array
    {
        $entidades = $analise['entidades'];
        $nomeBusca = $entidades['nome'] ?? null;
        $comando = $analise['comando_original'];
        
        if (!$nomeBusca) {
            return ['message' => '❌ Especifique o nome do pet.\nExemplo: "prescrever dipirona para Luna"'];
        }
        
        try {
            // Buscar pet
            $pet = $this->em->getRepository(\App\Entity\Pet::class)
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
            
            // Extrair medicamento
            $medicamento = '';
            if (preg_match('/prescrever\s+(\w+)/i', $comando, $matches)) {
                $medicamento = $matches[1];
            }
            
            if (!$medicamento) {
                return ['message' => '❌ Especifique o medicamento.\nExemplo: "prescrever dipirona para Luna"'];
            }
            
            // Buscar internação ativa
            $internacao = $this->em->getRepository(\App\Entity\Internacao::class)
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
                return ['message' => "❌ {$pet->getNome()} não está internado. Interne o pet primeiro."];
            }
            
            // Criar prescrição
            $prescricao = new \App\Entity\InternacaoPrescricao();
            $prescricao->setInternacaoId($internacao->getId());
            $prescricao->setMedicamento($medicamento);
            $prescricao->setDataHora(new \DateTime());
            $prescricao->setStatus('pendente');
            
            $this->em->persist($prescricao);
            $this->em->flush();
            
            $message = "💊 Prescrição registrada!\n\n";
            $message .= "🐕 Pet: {$pet->getNome()}\n";
            $message .= "💉 Medicamento: {$medicamento}\n";
            $message .= "📋 ID Prescrição: #{$prescricao->getId()}\n";
            $message .= "📅 Data: " . $prescricao->getDataHora()->format('d/m/Y H:i') . "\n";
            
            return ['message' => $message];
            
        } catch (\Exception $e) {
            return ['message' => "❌ Erro: " . $e->getMessage()];
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
            $pet = $this->em->getRepository(\App\Entity\Pet::class)
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
            
            $this->em->persist($vacina);
            $this->em->flush();
            
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
            $pet = $this->em->getRepository(\App\Entity\Pet::class)
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
            $internacao = $this->em->getRepository(\App\Entity\Internacao::class)
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
                $this->em->flush();
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
            $clientes = $this->em->getRepository(\App\Entity\Cliente::class)
                ->createQueryBuilder('c')
                ->where('LOWER(c.nome) LIKE :nome')
                ->andWhere('c.estabelecimentoId = :estab')
                ->setParameter('nome', '%' . strtolower($nomeBusca) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
            
            // Buscar pets
            $pets = $this->em->getRepository(\App\Entity\Pet::class)
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

    private function registrarLog(string $comando, array $analise, array $resultado, int $baseId): void
    {
        $log = sprintf(
            "[%s] Base: %d | Comando: %s | Ação: %s | Resultado: %s\n",
            date('Y-m-d H:i:s'),
            $baseId,
            $comando,
            $analise['acao'],
            $resultado['message']
        );

        file_put_contents(
            __DIR__ . '/../../var/log/ia_assistente_' . date('Ymd') . '.log',
            $log,
            FILE_APPEND
        );
    }
}
