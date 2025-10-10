<?php

namespace App\Repository;

use App\Entity\Vacina;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VacinaRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vacina::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    /**
     * Lista todas as vacinas de um pet
     */
    public function listarPorPet(int $baseId, int $petId): array
    {
        $sql = "SELECT 
                    v.id,
                    v.tipo,
                    v.data_aplicacao,
                    v.data_validade,
                    v.lote,
                    v.fabricante,
                    v.observacoes,
                    v.veterinario_id
                FROM {$_ENV['DBNAMETENANT']}.vacina v
                WHERE v.estabelecimento_id = :baseId
                  AND v.pet_id = :petId
                ORDER BY v.data_aplicacao DESC, v.id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('petId', $petId);
        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * Insere uma nova vacina no banco
     */
    public function save(int $baseId, Vacina $vacina): void
    {
        $sql = "INSERT INTO {$_ENV['DBNAMETENANT']}.vacina 
                    (estabelecimento_id, pet_id, tipo, data_aplicacao, data_validade, 
                     lote, fabricante, observacoes, veterinario_id)
                VALUES 
                    (:estabelecimento_id, :pet_id, :tipo, :data_aplicacao, :data_validade, 
                     :lote, :fabricante, :observacoes, :veterinario_id)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('estabelecimento_id', $baseId);
        $stmt->bindValue('pet_id', $vacina->getPetId());
        $stmt->bindValue('tipo', $vacina->getTipo());
        $stmt->bindValue('data_aplicacao', $vacina->getDataAplicacao()->format('Y-m-d'));
        $stmt->bindValue(
            'data_validade',
            $vacina->getDataValidade() ? $vacina->getDataValidade()->format('Y-m-d') : null
        );
        $stmt->bindValue('lote', $vacina->getLote());
        $stmt->bindValue('fabricante', $vacina->getFabricante());
        $stmt->bindValue('observacoes', $vacina->getObservacoes());
        $stmt->bindValue('veterinario_id', $vacina->getVeterinarioId());
        $stmt->executeStatement();

    }

    /**
     * Atualiza uma vacina existente
     */
    public function update(int $baseId, Vacina $vacina): void
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}.vacina 
                SET tipo = :tipo,
                    data_aplicacao = :data_aplicacao,
                    data_validade = :data_validade,
                    lote = :lote,
                    fabricante = :fabricante,
                    observacoes = :observacoes,
                    veterinario_id = :veterinario_id
                WHERE estabelecimento_id = :baseId AND id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('tipo', $vacina->getTipo());
        $stmt->bindValue('data_aplicacao', $vacina->getDataAplicacao()->format('Y-m-d'));
        $stmt->bindValue(
            'data_validade',
            $vacina->getDataValidade() ? $vacina->getDataValidade()->format('Y-m-d') : null
        );
        $stmt->bindValue('lote', $vacina->getLote());
        $stmt->bindValue('fabricante', $vacina->getFabricante());
        $stmt->bindValue('observacoes', $vacina->getObservacoes());
        $stmt->bindValue('veterinario_id', $vacina->getVeterinarioId());
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('id', $vacina->getId());
        $stmt->executeStatement();
    }


    /**
     * Remove uma vacina
     */
    public function delete(int $baseId, int $id): void
    {
        $sql = "DELETE FROM {$_ENV['DBNAMETENANT']}.vacina 
                WHERE estabelecimento_id = :baseId AND id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('id', $id);
        $stmt->executeStatement();
    }

    /**
     * Busca uma vacina específica
     */
    public function buscarPorId(int $baseId, int $id): ?array
    {
        $sql = "SELECT * FROM {$_ENV['DBNAMETENANT']}.vacina 
                WHERE estabelecimento_id = :baseId AND id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $stmt->bindValue('id', $id);
        $dados = $stmt->executeQuery()->fetchAssociative();

        return $dados ?: null;
    }
}
