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
            FROM {$_ENV['DBNAMETENANT']}.internacao i
            LEFT JOIN {$_ENV['DBNAMETENANT']}.pet p ON p.id = i.pet_id
            LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON c.id = i.dono_id
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
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.internacao 
                (data_inicio, motivo, status, pet_id, dono_id, estabelecimento_id)
                VALUES (:data_inicio, :motivo, :status, :pet_id, :dono_id, :estabelecimento_id)";

        $this->conn->executeQuery($sql, [
            'data_inicio' => $i->getDataInicio()->format('Y-m-d'),
            'motivo' => $i->getMotivo(),
            'status' => $i->getStatus(),
            'pet_id' => $i->getPetId(),
            'dono_id' => $i->getDonoId(),
            'estabelecimento_id' => $baseId,
        ]);
    }

    public function finalizarInternacao(int $baseId, int $id): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}.internacao 
                SET status = 'finalizada' 
                WHERE estabelecimento_id = :baseId AND id = :id";

        $this->conn->executeQuery($sql, [
            'baseId' => $baseId,
            'id' => $id,
        ]);
    }

    public function deletar(int $baseId, int $id): void
    {
        $this->conn->executeQuery("DELETE FROM {$_ENV['DBNAMETENANT']}.internacao 
            WHERE estabelecimento_id = :baseId AND id = :id", [
            'baseId' => $baseId,
            'id' => $id,
        ]);
    }

    public function buscarPorId(int $baseId, int $id): ?array
    {
        $sql = "SELECT * FROM {$_ENV['DBNAMETENANT']}.internacao 
                WHERE estabelecimento_id = :baseId AND id = :id";

        $dados = $this->conn->fetchAssociative($sql, [
            'baseId' => $baseId,
            'id' => $id
        ]);

        return $dados ?: null;
    }
}
