<?php

namespace App\Repository;

use App\Entity\Box;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Box|null find($id, $lockMode = null, $lockVersion = null)
 * @method Box|null findOneBy(array $criteria, array $orderBy = null)
 * @method Box[]    findAll()
 * @method Box[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BoxRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Box::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    /**
     * Lista todos os boxes de um estabelecimento com o pet atual (se ocupado).
     */
    public function findAllComOcupacao(int $baseId): array
    {
        $db  = $_ENV['DBNAMETENANT'];
        $sql = "
            SELECT
                b.id, b.numero, b.tipo, b.porte, b.estrutura, b.localizacao,
                b.status, b.suporte_soro, b.suporte_oxigenio, b.tem_aquecimento,
                b.tem_camera, b.peso_maximo_kg, b.valor_diaria, b.observacoes,
                b.created_at,
                -- internação ativa neste box
                i.id              AS internacao_id,
                i.data_inicio     AS internacao_inicio,
                i.alta_prevista,
                i.situacao,
                i.risco,
                p.nome            AS pet_nome,
                p.raca            AS pet_raca,
                c.nome            AS dono_nome,
                c.telefone        AS dono_telefone
            FROM {$db}.box b
            LEFT JOIN {$db}.internacao i
                ON  i.box      = b.numero
                AND i.estabelecimento_id = :baseId
                AND i.status   = 'ativa'
            LEFT JOIN {$db}.pet p ON p.id = i.pet_id
            LEFT JOIN {$db}.cliente c ON c.id = i.dono_id
            WHERE b.estabelecimento_id = :baseId
            ORDER BY b.tipo, b.numero ASC
        ";

        return $this->conn->fetchAllAssociative($sql, ['baseId' => $baseId]);
    }

    /**
     * Boxes disponíveis para seleção no formulário de internação.
     */
    public function findDisponiveisParaSelect(int $baseId): array
    {
        $db  = $_ENV['DBNAMETENANT'];
        $sql = "
            SELECT b.id, b.numero, b.tipo, b.porte, b.estrutura, b.localizacao
            FROM {$db}.box b
            WHERE b.estabelecimento_id = :baseId
              AND b.status = 'disponivel'
            ORDER BY b.tipo, b.numero ASC
        ";

        return $this->conn->fetchAllAssociative($sql, ['baseId' => $baseId]);
    }

    /**
     * Busca um box por ID, validando o estabelecimento.
     */
    public function findByIdAndBase(int $baseId, int $id): ?array
    {
        $db  = $_ENV['DBNAMETENANT'];
        $row = $this->conn->fetchAssociative(
            "SELECT * FROM {$db}.box WHERE id = :id AND estabelecimento_id = :baseId",
            ['id' => $id, 'baseId' => $baseId]
        );

        return $row ?: null;
    }

    /**
     * Insere um novo box e retorna o ID gerado.
     */
    public function inserir(int $baseId, Box $box): int
    {
        $db = $_ENV['DBNAMETENANT'];
        $this->conn->executeQuery(
            "INSERT INTO {$db}.box
                (estabelecimento_id, numero, tipo, porte, estrutura, localizacao, status,
                 suporte_soro, suporte_oxigenio, tem_aquecimento, tem_camera,
                 peso_maximo_kg, valor_diaria, observacoes, created_at, updated_at)
             VALUES
                (:eid, :numero, :tipo, :porte, :estrutura, :localizacao, :status,
                 :soro, :oxigenio, :aquecimento, :camera,
                 :peso_max, :valor_diaria, :observacoes, NOW(), NOW())",
            [
                'eid'          => $baseId,
                'numero'       => $box->getNumero(),
                'tipo'         => $box->getTipo(),
                'porte'        => $box->getPorte(),
                'estrutura'    => $box->getEstrutura(),
                'localizacao'  => $box->getLocalizacao(),
                'status'       => $box->getStatus(),
                'soro'         => (int) $box->isSuporteSoro(),
                'oxigenio'     => (int) $box->isSuporteOxigenio(),
                'aquecimento'  => (int) $box->isTemAquecimento(),
                'camera'       => (int) $box->isTemCamera(),
                'peso_max'     => $box->getPesoMaximoKg(),
                'valor_diaria' => $box->getValorDiaria(),
                'observacoes'  => $box->getObservacoes(),
            ]
        );

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Atualiza os dados de um box.
     */
    public function atualizar(int $baseId, Box $box): void
    {
        $db = $_ENV['DBNAMETENANT'];
        $this->conn->executeQuery(
            "UPDATE {$db}.box SET
                numero        = :numero,
                tipo          = :tipo,
                porte         = :porte,
                estrutura     = :estrutura,
                localizacao   = :localizacao,
                status        = :status,
                suporte_soro  = :soro,
                suporte_oxigenio = :oxigenio,
                tem_aquecimento  = :aquecimento,
                tem_camera    = :camera,
                peso_maximo_kg = :peso_max,
                valor_diaria  = :valor_diaria,
                observacoes   = :observacoes,
                updated_at    = NOW()
             WHERE id = :id AND estabelecimento_id = :eid",
            [
                'id'           => $box->getId(),
                'eid'          => $baseId,
                'numero'       => $box->getNumero(),
                'tipo'         => $box->getTipo(),
                'porte'        => $box->getPorte(),
                'estrutura'    => $box->getEstrutura(),
                'localizacao'  => $box->getLocalizacao(),
                'status'       => $box->getStatus(),
                'soro'         => (int) $box->isSuporteSoro(),
                'oxigenio'     => (int) $box->isSuporteOxigenio(),
                'aquecimento'  => (int) $box->isTemAquecimento(),
                'camera'       => (int) $box->isTemCamera(),
                'peso_max'     => $box->getPesoMaximoKg(),
                'valor_diaria' => $box->getValorDiaria(),
                'observacoes'  => $box->getObservacoes(),
            ]
        );
    }

    /**
     * Marca o box como disponível (liberação automática ao dar alta).
     */
    public function liberar(int $baseId, string $numeroBox): void
    {
        $db = $_ENV['DBNAMETENANT'];
        $this->conn->executeQuery(
            "UPDATE {$db}.box SET status = 'disponivel', updated_at = NOW()
             WHERE numero = :numero AND estabelecimento_id = :eid",
            ['numero' => $numeroBox, 'eid' => $baseId]
        );
    }

    /**
     * Marca o box como ocupado ao iniciar uma internação.
     */
    public function ocupar(int $baseId, string $numeroBox): void
    {
        $db = $_ENV['DBNAMETENANT'];
        $this->conn->executeQuery(
            "UPDATE {$db}.box SET status = 'ocupado', updated_at = NOW()
             WHERE numero = :numero AND estabelecimento_id = :eid",
            ['numero' => $numeroBox, 'eid' => $baseId]
        );
    }

    /**
     * Altera o status manualmente (manutenção, higienização etc.)
     */
    public function atualizarStatus(int $baseId, int $id, string $status): void
    {
        $db = $_ENV['DBNAMETENANT'];
        $this->conn->executeQuery(
            "UPDATE {$db}.box SET status = :status, updated_at = NOW()
             WHERE id = :id AND estabelecimento_id = :eid",
            ['id' => $id, 'status' => $status, 'eid' => $baseId]
        );
    }

    /**
     * Exclui um box (somente se não houver internação ativa).
     */
    public function excluir(int $baseId, int $id): void
    {
        $db = $_ENV['DBNAMETENANT'];
        $this->conn->executeQuery(
            "DELETE FROM {$db}.box WHERE id = :id AND estabelecimento_id = :eid",
            ['id' => $id, 'eid' => $baseId]
        );
    }

    /**
     * Retorna contadores por status para o dashboard.
     */
    public function contadoresPorStatus(int $baseId): array
    {
        $db  = $_ENV['DBNAMETENANT'];
        $rows = $this->conn->fetchAllAssociative(
            "SELECT status, COUNT(*) AS total
             FROM {$db}.box
             WHERE estabelecimento_id = :baseId
             GROUP BY status",
            ['baseId' => $baseId]
        );

        $result = ['disponivel' => 0, 'ocupado' => 0, 'manutencao' => 0, 'reservado' => 0, 'higienizacao' => 0];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['total'];
        }
        return $result;
    }
}
