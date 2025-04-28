<?php

namespace App\Service;

use App\Entity\Atendimento;
use App\Entity\Pet;
use App\Entity\Procedimento;
use App\Repository\AtendimentoRepository;
use App\Repository\PetRepository;
use App\Repository\ProcedimentoRepository;
use Doctrine\ORM\EntityManagerInterface;

class AtendimentoService
{
    private $atendimentoRepository;
    private $petRepository;
    private $procedimentoRepository;
    private $entityManager;

    public function __construct(
        AtendimentoRepository $atendimentoRepository,
        PetRepository $petRepository,
        ProcedimentoRepository $procedimentoRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->atendimentoRepository = $atendimentoRepository;
        $this->petRepository = $petRepository;
        $this->procedimentoRepository = $procedimentoRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Cria um novo atendimento
     *
     * @param int $petId
     * @param array $procedimentoIds
     * @param string|null $observacoes
     * @param string $dataAtendimento
     * @param string $horaAtendimento
     * @return array
     */
    public function criarAtendimento(
        int $petId,
        array $procedimentoIds,
        ?string $observacoes,
        string $dataAtendimento,
        string $horaAtendimento
    ): array {
        try {
            $pet = $this->petRepository->find($petId);
            
            if (!$pet) {
                return [
                    'sucesso' => false,
                    'mensagem' => 'Pet não encontrado'
                ];
            }
            
            $atendimento = new Atendimento();
            $atendimento->setPet($pet);
            $atendimento->setObservacoes($observacoes);
            
            // Configurar data e hora
            $dataHora = new \DateTime($dataAtendimento . ' ' . $horaAtendimento);
            $atendimento->setDataHora($dataHora);
            
            // Adicionar procedimentos
            foreach ($procedimentoIds as $procedimentoId) {
                $procedimento = $this->procedimentoRepository->find($procedimentoId);
                if ($procedimento) {
                    $atendimento->addProcedimento($procedimento);
                }
            }
            
            $this->atendimentoRepository->save($atendimento, true);
            
            return [
                'sucesso' => true,
                'mensagem' => 'Atendimento criado com sucesso',
                'atendimento' => $atendimento
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao criar atendimento: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Finaliza um atendimento
     *
     * @param Atendimento $atendimento
     * @param string|null $diagnostico
     * @param string|null $prescricao
     * @param string|null $observacoes
     * @return array
     */
    public function finalizarAtendimento(
        Atendimento $atendimento,
        ?string $diagnostico,
        ?string $prescricao,
        ?string $observacoes
    ): array {
        try {
            if ($atendimento->getStatus() === 'Agendado') {
                $atendimento->setStatus('Em Andamento');
            } else if ($atendimento->getStatus() === 'Em Andamento') {
                $atendimento->setStatus('Finalizado');
            } else {
                return [
                    'sucesso' => false,
                    'mensagem' => 'Atendimento não pode ser finalizado no status atual'
                ];
            }
            
            $atendimento->setDiagnostico($diagnostico);
            $atendimento->setPrescricao($prescricao);
            
            if ($observacoes) {
                $atendimento->setObservacoes($observacoes);
            }
            
            $this->atendimentoRepository->save($atendimento, true);
            
            return [
                'sucesso' => true,
                'mensagem' => 'Atendimento atualizado com sucesso',
                'atendimento' => $atendimento
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao finalizar atendimento: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancela um atendimento
     *
     * @param Atendimento $atendimento
     * @param string|null $motivoCancelamento
     * @return array
     */
    public function cancelarAtendimento(
        Atendimento $atendimento,
        ?string $motivoCancelamento
    ): array {
        try {
            if ($atendimento->getStatus() === 'Finalizado') {
                return [
                    'sucesso' => false,
                    'mensagem' => 'Atendimento finalizado não pode ser cancelado'
                ];
            }
            
            $atendimento->setStatus('Cancelado');
            
            if ($motivoCancelamento) {
                $observacoes = $atendimento->getObservacoes() ?? '';
                $observacoes .= "\n\nMotivo do cancelamento: " . $motivoCancelamento;
                $atendimento->setObservacoes($observacoes);
            }
            
            $this->atendimentoRepository->save($atendimento, true);
            
            return [
                'sucesso' => true,
                'mensagem' => 'Atendimento cancelado com sucesso',
                'atendimento' => $atendimento
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao cancelar atendimento: ' . $e->getMessage()
            ];
        }
    }
}
