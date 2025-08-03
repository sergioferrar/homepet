<?php

namespace App\Repository;

use App\Entity\Internacao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InternacaoRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Internacao::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    /**
     * Busca uma internação completa por ID, incluindo dados do pet, tutor e veterinário.
     * Inclui dados mock/exemplo para eventos da linha do tempo.
     *
     * @param int $baseId O ID do estabelecimento.
     * @param int $internacaoId O ID da internação.
     * @return array|null Retorna um array associativo com os dados da internação ou null se não encontrada.
     */
    public function findInternacaoCompleta(int $baseId, int $internacaoId): ?array
    {
        $sql = "
            SELECT
                i.id,
                i.data_inicio,
                i.motivo,
                i.status,
                i.situacao,    -- Adicionado: campo 'situacao' da entidade Internacao
                i.risco,       -- Adicionado: campo 'risco' da entidade Internacao
                i.box,         -- Adicionado: campo 'box' da entidade Internacao
                i.alta_prevista, -- Adicionado: campo 'alta_prevista' da entidade Internacao
                i.anotacoes,   -- Adicionado: campo 'anotacoes' (alergias_marcacoes)
                p.id AS pet_id,
                p.nome AS pet_nome,
                p.raca AS pet_raca,
                p.sexo AS pet_sexo,
                p.idade AS pet_idade,
                c.id AS dono_id,
                c.nome AS dono_nome,
                c.telefone AS dono_telefone,
                v.id AS veterinario_id,
                v.nome AS veterinario_nome,
                v.especialidade AS veterinario_especialidade
            FROM " . $_ENV['DBNAMETENANT'] . ".internacao i
            LEFT JOIN " . $_ENV['DBNAMETENANT'] . ".pet p ON p.id = i.pet_id
            LEFT JOIN " . $_ENV['DBNAMETENANT'] . ".cliente c ON c.id = p.dono_id
            LEFT JOIN " . $_ENV['DBNAMETENANT'] . ".veterinario v ON v.id = i.veterinario_id -- Assumindo que internacao tem veterinario_id
            WHERE i.estabelecimento_id = :baseId AND i.id = :internacaoId
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('internacaoId', $internacaoId);
        $result = $stmt->executeQuery();

        $internacao = $result->fetchAssociative();

        if (!$internacao) {
            return null;
        }

        // Adicionar dados mock/exemplo para a linha do tempo (eventos)
        // Em um sistema real, você buscaria isso de uma tabela de eventos de internação.
        $internacao['eventos'] = [
            [
                'data' => (new \DateTime())->format('Y-m-d H:i:s'),
                'titulo' => 'Execução: Alopurinol 100mg',
                'descricao' => 'Registrado por Veterinário(a): Animal Vet em ' . (new \DateTime())->format('d/m/Y H:i') . ' | Programado para ' . (new \DateTime())->format('d/m/Y H:i') . ' | Executado em ' . (new \DateTime())->format('d/m/Y H:i'),
            ],
            [
                'data' => (new \DateTime())->modify('-1 hour')->format('Y-m-d H:i:s'),
                'titulo' => 'Execução: Glicolipol',
                'descricao' => 'Registrado por Veterinário(a): Animal Vet em ' . (new \DateTime())->modify('-1 hour')->format('d/m/Y H:i') . ' | Programado para ' . (new \DateTime())->modify('-1 hour')->format('d/m/Y H:i') . ' | Executado em ' . (new \DateTime())->modify('-1 hour')->format('d/m/Y H:i'),
            ],
            [
                'data' => (new \DateTime())->modify('-2 hours')->format('Y-m-d H:i:s'),
                'titulo' => 'Execução: Leucogen',
                'descricao' => 'Registrado por Veterinário(a): Animal Vet em ' . (new \DateTime())->modify('-2 hours')->format('d/m/Y H:i') . ' | Programado para ' . (new \DateTime())->modify('-2 hours')->format('d/m/Y H:i') . ' | Executado em ' . (new \DateTime())->modify('-2 hours')->format('d/m/Y H:i'),
            ],
            [
                'data' => (new \DateTime())->modify('-3 hours')->format('Y-m-d H:i:s'),
                'titulo' => 'Execução: ETNA 1 comprimido',
                'descricao' => 'Registrado por Veterinário(a): Animal Vet em ' . (new \DateTime())->modify('-3 hours')->format('d/m/Y H:i') . ' | Programado para ' . (new \DateTime())->modify('-3 hours')->format('d/m/Y H:i') . ' | Executado em ' . (new \DateTime())->modify('-3 hours')->format('d/m/Y H:i'),
            ],
        ];

        // Calcular a duração da internação (exemplo simples)
        $dataInicio = new \DateTime($internacao['data_inicio']);
        $agora = new \DateTime();
        $interval = $agora->diff($dataInicio);
        $internacao['duracao'] = $interval->format('%a dias, %h horas e %i minutos');
        $internacao['pet_idade'] = $internacao['pet_idade'] ?? 'idade não informada'; // Garante que não seja nulo

        return $internacao;
    }

    // ... (outros métodos existentes) ...

    public function listarInternacoesAtivas(int $baseId): array
    {
        $sql = "
            SELECT 
                i.id,
                i.data_inicio,
                i.motivo,
                i.status,
                p.nome AS nome_pet,
                c.nome AS nome_cliente
            FROM " . $_ENV['DBNAMETENANT'] . ".internacao i
            LEFT JOIN " . $_ENV['DBNAMETENANT'] . ".pet p ON p.id = i.pet_id
            LEFT JOIN " . $_ENV['DBNAMETENANT'] . ".cliente c ON c.id = i.dono_id
            WHERE i.estabelecimento_id = :baseId
              AND i.status = 'ativa'
            ORDER BY i.data_inicio DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    public function inserirInternacao(int $baseId, Internacao $i): void
    {
        $sql = "INSERT INTO " . $_ENV['DBNAMETENANT'] . ".internacao 
                (data_inicio, motivo, status, pet_id, dono_id, estabelecimento_id, situacao, risco, veterinario_id, box, alta_prevista, diagnostico, prognostico, anotacoes)
                VALUES (:data_inicio, :motivo, :status, :pet_id, :dono_id, :estabelecimento_id, :situacao, :risco, :veterinario_id, :box, :alta_prevista, :diagnostico, :prognostico, :anotacoes)";

        $this->conn->executeQuery($sql, [
            'data_inicio' => $i->getDataInicio()->format('Y-m-d H:i:s'), // Ajustado para incluir hora
            'motivo' => $i->getMotivo(),
            'status' => $i->getStatus(),
            'pet_id' => $i->getPetId(),
            'dono_id' => $i->getDonoId(),
            'estabelecimento_id' => $baseId,
            'situacao' => $i->getSituacao(),
            'risco' => $i->getRisco(),
            'veterinario_id' => $i->getVeterinarioId(),
            'box' => $i->getBox(),
            'alta_prevista' => $i->getAltaPrevista() ? $i->getAltaPrevista()->format('Y-m-d') : null,
            'diagnostico' => $i->getDiagnostico(),
            'prognostico' => $i->getPrognostico(),
            'anotacoes' => $i->getAnotacoes(),
        ]);
    }

    public function finalizarInternacao(int $baseId, int $id): void
    {
        $sql = "UPDATE " . $_ENV['DBNAMETENANT'] . ".internacao 
                SET status = 'finalizada' 
                WHERE estabelecimento_id = :baseId AND id = :id";

        $this->conn->executeQuery($sql, [
            'baseId' => $baseId,
            'id' => $id,
        ]);
    }

    public function deletar(int $baseId, int $id): void
    {
        $this->conn->executeQuery("DELETE FROM " . $_ENV['DBNAMETENANT'] . ".internacao 
            WHERE estabelecimento_id = :baseId AND id = :id", [
            'baseId' => $baseId,
            'id' => $id,
        ]);
    }

    public function buscarPorId(int $baseId, int $id): ?array
    {
        $sql = "SELECT * FROM " . $_ENV['DBNAMETENANT'] . ".internacao 
                WHERE estabelecimento_id = :baseId AND id = :id";

        $dados = $this->conn->fetchAssociative($sql, [
            'baseId' => $baseId,
            'id' => $id
        ]);

        return $dados ?: null;
    }
}