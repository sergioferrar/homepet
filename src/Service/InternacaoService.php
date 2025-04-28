<?php

namespace App\Service;

use App\Entity\Internacao;
use App\Entity\Pet;
use App\Repository\InternacaoRepository;
use App\Repository\PetRepository;
use Doctrine\ORM\EntityManagerInterface;

class InternacaoService
{
    private $internacaoRepository;
    private $petRepository;
    private $entityManager;

    public function __construct(
        InternacaoRepository $internacaoRepository,
        PetRepository $petRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->internacaoRepository = $internacaoRepository;
        $this->petRepository = $petRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Cria uma nova internação
     *
     * @param int $petId
     * @param string $motivo
     * @param string|null $observacoes
     * @param string $dataInicio
     * @return array
     */
    public function criarInternacao(
        int $petId,
        string $motivo,
        ?string $observacoes,
        string $dataInicio
    ): array {
        try {
            $pet = $this->petRepository->find($petId);
            
            if (!$pet) {
                return [
                    'sucesso' => false,
                    'mensagem' => 'Pet não encontrado'
                ];
            }
            
            // Verificar se já existe internação ativa para este pet
            $internacoesAtivas = $this->internacaoRepository->findBy([
                'pet' => $pet,
                'status' => 'ativa'
            ]);
            
            if (count($internacoesAtivas) > 0) {
                return [
                    'sucesso' => false,
                    'mensagem' => 'Este pet já possui uma internação ativa'
                ];
            }
            
            $internacao = new Internacao();
            $internacao->setPet($pet);
            $internacao->setMotivo($motivo);
            $internacao->setObservacoes($observacoes);
            $internacao->setDataInicio(new \DateTime($dataInicio));
            $internacao->setStatus('ativa');
            
            $this->internacaoRepository->save($internacao, true);
            
            return [
                'sucesso' => true,
                'mensagem' => 'Internação iniciada com sucesso',
                'internacao' => $internacao
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao criar internação: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Finaliza uma internação
     *
     * @param Internacao $internacao
     * @param string|null $observacoesSaida
     * @param string $dataSaida
     * @return array
     */
    public function finalizarInternacao(
        Internacao $internacao,
        ?string $observacoesSaida,
        string $dataSaida
    ): array {
        try {
            if ($internacao->getStatus() !== 'ativa') {
                return [
                    'sucesso' => false,
                    'mensagem' => 'Apenas internações ativas podem ser finalizadas'
                ];
            }
            
            $dataSaidaObj = new \DateTime($dataSaida);
            
            // Verificar se a data de saída é posterior à data de início
            if ($dataSaidaObj < $internacao->getDataInicio()) {
                return [
                    'sucesso' => false,
                    'mensagem' => 'A data de saída não pode ser anterior à data de início'
                ];
            }
            
            $internacao->setDataSaida($dataSaidaObj);
            $internacao->setObservacoesSaida($observacoesSaida);
            $internacao->setStatus('finalizada');
            
            $this->internacaoRepository->save($internacao, true);
            
            return [
                'sucesso' => true,
                'mensagem' => 'Internação finalizada com sucesso',
                'internacao' => $internacao
            ];
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao finalizar internação: ' . $e->getMessage()
            ];
        }
    }
}
