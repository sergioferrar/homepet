<?php

namespace App\Repository;

use App\Entity\Internacao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;

class InternacaoRepository extends ServiceEntityRepository
{
    private Connection $conn;

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
            SELECT i.id, i.data_inicio, i.motivo, i.status, i.situacao, i.risco, i.box, i.alta_prevista, i.anotacoes,
                p.id   AS pet_id, p.nome AS pet_nome, p.raca AS pet_raca, p.sexo AS pet_sexo, p.idade AS pet_idade,
                c.id   AS dono_id, c.nome AS dono_nome, c.telefone AS dono_telefone,
                v.id   AS veterinario_id, v.nome AS veterinario_nome, v.especialidade AS veterinario_especialidade
            FROM homepet_" . $baseId . ".internacao i
            LEFT JOIN homepet_" . $baseId . ".pet p ON p.id = i.pet_id
            LEFT JOIN homepet_" . $baseId . ".cliente c ON c.id = p.dono_id
            LEFT JOIN homepet_" . $baseId . ".veterinario v ON v.id = i.veterinario_id
            WHERE i.estabelecimento_id = :baseId AND i.id = :internacaoId
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('internacaoId', $internacaoId);
        $internacao = $stmt->executeQuery()->fetchAssociative();

        if (!$internacao) {
            return null;
        }

        $internacao['eventos'] = [];

        $dataInicio = new \DateTime($internacao['data_inicio']);
        $interval = (new \DateTime())->diff($dataInicio);
        $internacao['duracao'] = $interval->format('%a dias, %h horas e %i minutos');
        $internacao['pet_idade'] = $internacao['pet_idade'] ?? 'idade não informada';

        return $internacao;
    }

    public function listarEventosPorInternacao(int $baseId, int $internacaoId): array
    {
        $sql = "
            SELECT id, estabelecimento_id, internacao_id, pet_id, tipo, titulo, descricao, data_hora, criado_em, status
            FROM homepet_" . $baseId . ".internacao_evento
            WHERE estabelecimento_id = :baseId AND internacao_id = :internacaoId
            ORDER BY data_hora DESC, id DESC
        ";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $baseId,
            'internacaoId' => $internacaoId,
        ]);
    }


    public function inserirEvento(
        int $baseId,
        int $internacaoId,
        int $petId,
        string $tipo,
        string $titulo,
        ?string $descricao,
        ?\DateTimeInterface $dataHora = null,
        string $status = 'pendente'
    ): int {
        $sql = "INSERT INTO homepet_" . $baseId . ".internacao_evento
                (estabelecimento_id, internacao_id, pet_id, tipo, titulo, descricao, data_hora, criado_em, status)
                VALUES (:baseId, :internacaoId, :petId, :tipo, :titulo, :descricao, :data_hora, :criado_em, :status)";

        $this->conn->executeQuery($sql, [
            'baseId'       => $baseId,
            'internacaoId' => $internacaoId,
            'petId'        => $petId,
            'tipo'         => $tipo,
            'titulo'       => $titulo,
            'descricao'    => $descricao,
            'data_hora'    => ($dataHora ?? new \DateTime())->format('Y-m-d H:i:s'),
            'criado_em'    => (new \DateTime())->format('Y-m-d H:i:s'),
            'status'       => $status,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function listarInternacoesAtivas(int $baseId): array
    {
        $sql = "
            SELECT  
                i.id,
                i.pet_id,
                i.data_inicio,
                i.motivo,
                i.status,
                p.nome AS pet_nome,
                c.nome AS dono_nome
            FROM homepet_" . $baseId . ".internacao i
            LEFT JOIN homepet_" . $baseId . ".pet p ON p.id = i.pet_id
            LEFT JOIN homepet_" . $baseId . ".cliente c ON c.id = i.dono_id
            WHERE i.estabelecimento_id = :baseId
              AND i.status = 'ativa'
              AND i.pet_id IS NOT NULL
              AND i.pet_id > 0
            ORDER BY i.data_inicio DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }


    public function inserirInternacao(int $baseId, Internacao $i): int
    {
        $motivo = (string) $i->getMotivo();
        $motivo = mb_substr(trim($motivo), 0, 255);

        $this->conn->executeQuery(
            "INSERT INTO homepet_" . $baseId . ".internacao 
             (data_inicio, motivo, status, pet_id, dono_id, estabelecimento_id, situacao, risco, veterinario_id, box, alta_prevista, diagnostico, prognostico, anotacoes)
             VALUES (:data_inicio, :motivo, :status, :pet_id, :dono_id, :estabelecimento_id, :situacao, :risco, :veterinario_id, :box, :alta_prevista, :diagnostico, :prognostico, :anotacoes)",
            [
                'data_inicio'        => $i->getDataInicio()->format('Y-m-d H:i:s'),
                'motivo'             => $motivo, // ← usa o truncado
                'status'             => $i->getStatus(),
                'pet_id'             => $i->getPetId(),
                'dono_id'            => $i->getDonoId(),
                'estabelecimento_id' => $baseId,
                'situacao'           => $i->getSituacao(),
                'risco'              => $i->getRisco(),
                'veterinario_id'     => $i->getVeterinarioId(),
                'box'                => $i->getBox(),
                'alta_prevista'      => $i->getAltaPrevista() ? $i->getAltaPrevista()->format('Y-m-d') : null,
                'diagnostico'        => $i->getDiagnostico(),
                'prognostico'        => $i->getPrognostico(),
                'anotacoes'          => $i->getAnotacoes(),
            ]
        );

        return (int) $this->conn->lastInsertId();
    }


    public function finalizarInternacao(int $baseId, int $id): void
    {
        $sql = "UPDATE homepet_" . $baseId . ".internacao 
                SET status = 'finalizada' 
                WHERE estabelecimento_id = :baseId AND id = :id";

        $this->conn->executeQuery($sql, [
            'baseId' => $baseId,
            'id' => $id,
        ]);
    }

    public function deletar(int $baseId, int $id): void
    {
        $this->conn->executeQuery("DELETE FROM homepet_" . $baseId . ".internacao 
            WHERE estabelecimento_id = :baseId AND id = :id", [
            'baseId' => $baseId,
            'id' => $id,
        ]);
    }

    public function buscarPorId(int $baseId, int $id): ?array
    {
        $sql = "SELECT * FROM homepet_" . $baseId . ".internacao 
                WHERE estabelecimento_id = :baseId AND id = :id";

        $dados = $this->conn->fetchAssociative($sql, [
            'baseId' => $baseId,
            'id' => $id
        ]);

        return $dados ?: null;
    }

    public function findAtivaIdByPet(int $baseId, int $petId): ?int
    {
        $sql = "SELECT id
                FROM homepet_" . $baseId . ".internacao
                WHERE estabelecimento_id = :baseId
                  AND pet_id = :petId
                  AND status = 'ativa'
                ORDER BY data_inicio DESC
                LIMIT 1";

        $row = $this->conn->fetchAssociative($sql, [
            'baseId' => $baseId,
            'petId'  => $petId,
        ]);

        return $row['id'] ?? null;
    }

 public function listarTimelinePet(int $baseId, int $petId, int $limit = 200): array
    {
        $db = "homepet_{$baseId}";
        // Ajuste aqui se preferir outra collation:
        $collation = 'utf8mb4_unicode_ci';

        $sql = "
            SELECT 
                CAST(CONCAT(c.data, ' ', c.hora) AS DATETIME)                        AS data_hora,
                CONVERT(COALESCE(c.tipo, 'Consulta') USING utf8mb4)            AS tipo,
                CONVERT(CONCAT('Atendimento - ', COALESCE(c.tipo,'Consulta')) USING utf8mb4) AS titulo,
                CONVERT(NULLIF(c.observacoes, '') USING utf8mb4)               AS descricao
            FROM homepet_{$baseId}.consulta c
            WHERE c.estabelecimento_id = :baseId
              AND c.pet_id = :petId

            UNION ALL

            SELECT 
                CAST(CONCAT(r.data, ' 00:00:00') AS DATETIME)                         AS data_hora,
                CONVERT('Receita' USING utf8mb4)                 AS tipo,
                CONVERT(COALESCE(r.resumo, 'Receita emitida') USING utf8mb4)   AS titulo,
                CAST(NULL AS CHAR)                                AS descricao
            FROM homepet_{$baseId}.receita r
            WHERE r.estabelecimento_id = :baseId
              AND r.pet_id = :petId

            UNION ALL

            SELECT 
                CAST(ie.data_hora AS DATETIME)                                        AS data_hora,
                CONVERT(ie.tipo USING utf8mb4)                   AS tipo,
                CONVERT(ie.titulo USING utf8mb4)                 AS titulo,
                CONVERT(ie.descricao USING utf8mb4)              AS descricao
            FROM homepet_{$baseId}.internacao_evento ie
            WHERE ie.estabelecimento_id = :baseId
              AND ie.pet_id = :petId

            ORDER BY data_hora DESC
            LIMIT :limit
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('petId',  $petId);
        $stmt->bindValue('limit',  $limit, \PDO::PARAM_INT);

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    public function listarTimelineInternacao(int $baseId, int $internacaoId, int $limit = 200): array
    {
        $db = "homepet_{$baseId}";

        $sql = "
            SELECT 
                ie.id,
                ie.estabelecimento_id,
                ie.internacao_id,
                ie.pet_id,
                ie.tipo,
                ie.titulo,
                ie.descricao,
                ie.data_hora,
                ie.criado_em
            FROM homepet_{$baseId}.internacao_evento ie
            WHERE ie.estabelecimento_id = :baseId
              AND ie.internacao_id = :internacaoId
            ORDER BY ie.data_hora DESC, ie.id DESC
            LIMIT :limit
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId',       $baseId);
        $stmt->bindValue('internacaoId', $internacaoId);
        $stmt->bindValue('limit',        $limit, \PDO::PARAM_INT);

        return $stmt->executeQuery()->fetchAllAssociative();
    }



    public function findUltimaIdByPet(int $baseId, int $petId): ?int
    {
        $sql = "SELECT id
                FROM homepet_" . $baseId . ".internacao
                WHERE estabelecimento_id = :baseId
                  AND pet_id = :petId
                ORDER BY data_inicio DESC
                LIMIT 1";

        $row = $this->conn->fetchAssociative($sql, [
            'baseId' => $baseId,
            'petId'  => $petId,
        ]);

        return $row['id'] ?? null;
    }

    public function listarInternacoesPorPet(int $baseId, int $petId): array
    {
        $sql = "
            SELECT id, data_inicio, status, motivo, situacao, risco, box
            FROM homepet_" . $baseId . ".internacao
            WHERE estabelecimento_id = :baseId
              AND pet_id = :petId
            ORDER BY data_inicio DESC, id DESC
        ";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $baseId,
            'petId'  => $petId,
        ]);
    }

    public function marcarMedicacaoComoExecutada(int $baseId, int $eventoId): void
    {
        $sql = "UPDATE homepet_" . $baseId . ".internacao_evento
                SET tipo = 'medicacao_exec', status = 'executado'
                WHERE estabelecimento_id = :baseId AND id = :eventoId";

        $this->conn->executeQuery($sql, [
            'baseId'  => $baseId,
            'eventoId'=> $eventoId,
        ]);
    }

    public function atualizarStatus(int $baseId, int $id, string $status): void
    {
        $sql = "UPDATE homepet_" . $baseId . ".internacao 
                SET status = :status 
                WHERE estabelecimento_id = :baseId AND id = :id";
        $this->conn->executeQuery($sql, [
            'baseId' => $baseId,
            'id'     => $id,
            'status' => $status,
        ]);
    }

/**
     * ✅ NOVO MÉTODO: Registra a conclusão da internação
     * 
     * Quando internação é finalizada (alta, óbito, cancelada):
     * - Grava quando foi finalizada (data_conclusao)
     * - Grava por que foi finalizada (motivo_conclusao)
     * 
     * @param int $baseId Estabelecimento
     * @param int $internacaoId ID da internação
     * @param \DateTime $dataConclusao Quando foi finalizada
     * @param string $motivoConclusao Por que foi finalizada (alta, obito, cancelada)
     * @return bool
     */
    public function atualizarConclusao(
        int $baseId,
        int $internacaoId,
        \DateTime $dataConclusao,
        string $motivoConclusao
    ): bool {
        try {
            $sql = "
                UPDATE homepet_" . $baseId . ".internacao
                SET 
                    data_conclusao = :data_conclusao,
                    motivo_conclusao = :motivo_conclusao
                WHERE id = :id
                  AND estabelecimento_id = :baseId
            ";
 
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue('id', $internacaoId, \PDO::PARAM_INT);
            $stmt->bindValue('baseId', $baseId, \PDO::PARAM_INT);
            $stmt->bindValue('data_conclusao', $dataConclusao->format('Y-m-d H:i:s'));
            $stmt->bindValue('motivo_conclusao', $motivoConclusao);
 
            return $stmt->executeStatement() > 0;
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Erro ao registrar conclusão da internação: " . $e->getMessage()
            );
        }
    }
 
    /**
     * ✅ NOVO MÉTODO: Busca internações ativas (melhorado)
     * 
     * Agora filtra por status = 'ativa' APENAS
     * Não mostra internações finalizadas (status = 'finalizada')
     * 
     * @param int $baseId Estabelecimento
     * @return array
     */
    public function listarAtivas(int $baseId): array
    {
        $sql = "
            SELECT
                i.id,
                i.pet_id,
                i.dono_id,
                i.status,
                i.motivo_conclusao,
                i.data_inicio,
                i.data_conclusao,
                i.box,
                i.diagnostico,
                i.prognostico,
                i.risco,
                p.nome AS pet_nome,
                p.especie,
                p.raca,
                c.nome AS dono_nome
            FROM homepet_" . $baseId . ".internacao i
            LEFT JOIN homepet_" . $baseId . ".pet p ON p.id = i.pet_id
            LEFT JOIN homepet_" . $baseId . ".cliente c ON c.id = i.dono_id
            WHERE i.estabelecimento_id = :baseId
              AND i.status = 'ativa'
              AND i.pet_id IS NOT NULL
              AND i.pet_id > 0
            ORDER BY i.data_inicio DESC
        ";
 
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery();
 
        return $result->fetchAllAssociative();
    }
 
    /**
     * ✅ NOVO MÉTODO: Busca internações finalizadas (para relatórios)
     * 
     * Mostra internações concluídas com motivo
     * Útil para relatórios de altas, óbitos e cancelamentos
     * 
     * @param int $baseId Estabelecimento
     * @param string|null $motivo Filtro opcional: 'alta', 'obito', 'cancelada'
     * @param \DateTime|null $dataInicio Para filtrar por período
     * @param \DateTime|null $dataFim Para filtrar por período
     * @return array
     */
    public function listarFinalizadas(
        int $baseId,
        ?string $motivo = null,
        ?\DateTime $dataInicio = null,
        ?\DateTime $dataFim = null
    ): array {
        $sql = "
            SELECT
                i.id,
                i.pet_id,
                i.dono_id,
                i.status,
                i.motivo_conclusao,
                i.data_inicio,
                i.data_conclusao,
                i.box,
                i.diagnostico,
                i.prognostico,
                i.risco,
                p.nome AS pet_nome,
                p.especie,
                p.raca,
                c.nome AS dono_nome,
                DATEDIFF(i.data_conclusao, i.data_inicio) AS dias_internacao
            FROM homepet_" . $baseId . ".internacao i
            LEFT JOIN homepet_" . $baseId . ".pet p ON p.id = i.pet_id
            LEFT JOIN homepet_" . $baseId . ".cliente c ON c.id = i.dono_id
            WHERE i.estabelecimento_id = :baseId
              AND i.status = 'finalizada'
              AND i.pet_id IS NOT NULL
        ";
 
        if ($motivo) {
            $sql .= " AND i.motivo_conclusao = :motivo";
        }
 
        if ($dataInicio) {
            $sql .= " AND i.data_conclusao >= :dataInicio";
        }
 
        if ($dataFim) {
            $sql .= " AND i.data_conclusao <= :dataFim";
        }
 
        $sql .= " ORDER BY i.data_conclusao DESC";
 
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
 
        if ($motivo) {
            $stmt->bindValue('motivo', $motivo);
        }
 
        if ($dataInicio) {
            $stmt->bindValue('dataInicio', $dataInicio->format('Y-m-d H:i:s'));
        }
 
        if ($dataFim) {
            $stmt->bindValue('dataFim', $dataFim->format('Y-m-d 23:59:59'));
        }
 
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }
 
    /**
     * ✅ NOVO MÉTODO: Conta internações por tipo de conclusão
     * 
     * Retorna estatísticas de finalizações
     * Ex: [
     *    'alta' => 45,
     *    'obito' => 3,
     *    'cancelada' => 2
     * ]
     * 
     * @param int $baseId Estabelecimento
     * @param \DateTime|null $dataInicio Período (opcional)
     * @param \DateTime|null $dataFim Período (opcional)
     * @return array Contagem por tipo
     */
    public function estatisticasFinalizacao(
        int $baseId,
        ?\DateTime $dataInicio = null,
        ?\DateTime $dataFim = null
    ): array {
        $sql = "
            SELECT
                motivo_conclusao,
                COUNT(*) as quantidade
            FROM homepet_" . $baseId . ".internacao
            WHERE estabelecimento_id = :baseId
              AND status = 'finalizada'
        ";
 
        if ($dataInicio) {
            $sql .= " AND data_conclusao >= :dataInicio";
        }
 
        if ($dataFim) {
            $sql .= " AND data_conclusao <= :dataFim";
        }
 
        $sql .= " GROUP BY motivo_conclusao";
 
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
 
        if ($dataInicio) {
            $stmt->bindValue('dataInicio', $dataInicio->format('Y-m-d H:i:s'));
        }
 
        if ($dataFim) {
            $stmt->bindValue('dataFim', $dataFim->format('Y-m-d 23:59:59'));
        }
 
        $result = $stmt->executeQuery();
        $rows = $result->fetchAllAssociative();
 
        // Formata resultado como array associativo
        $stats = ['alta' => 0, 'obito' => 0, 'cancelada' => 0];
        foreach ($rows as $row) {
            $motivo = $row['motivo_conclusao'] ?? 'desconhecido';
            $stats[$motivo] = (int) $row['quantidade'];
        }
 
        return $stats;
    }
 
    /**
     * ✅ NOVO MÉTODO: Calcula tempo médio de internação
     * 
     * @param int $baseId Estabelecimento
     * @param \DateTime|null $dataInicio Período (opcional)
     * @param \DateTime|null $dataFim Período (opcional)
     * @return float|null Dias de internação médios
     */
    public function tempoMedioInternacao(
        int $baseId,
        ?\DateTime $dataInicio = null,
        ?\DateTime $dataFim = null
    ): ?float {
        $sql = "
            SELECT
                AVG(DATEDIFF(data_conclusao, data_inicio)) as dias_medio
            FROM homepet_" . $baseId . ".internacao
            WHERE estabelecimento_id = :baseId
              AND status = 'finalizada'
              AND data_conclusao IS NOT NULL
              AND data_inicio IS NOT NULL
        ";
 
        if ($dataInicio) {
            $sql .= " AND data_conclusao >= :dataInicio";
        }
 
        if ($dataFim) {
            $sql .= " AND data_conclusao <= :dataFim";
        }
 
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
 
        if ($dataInicio) {
            $stmt->bindValue('dataInicio', $dataInicio->format('Y-m-d H:i:s'));
        }
 
        if ($dataFim) {
            $stmt->bindValue('dataFim', $dataFim->format('Y-m-d 23:59:59'));
        }
 
        $result = $stmt->executeQuery();
        $row = $result->fetchAssociative();
 
        return $row['dias_medio'] ? (float) $row['dias_medio'] : null;
    }

}