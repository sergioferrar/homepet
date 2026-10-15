<?php

namespace App\Repository;

use App\Entity\ClienteCreditoMovimento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Operações de crédito do cliente. O saldo é SEMPRE recalculado — não há
 * campo denormalizado em `cliente`.
 */
class ClienteCreditoMovimentoRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClienteCreditoMovimento::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function saldoDoCliente(int $baseId, int $clienteId): float
    {
        $sql = "SELECT COALESCE(SUM(
                    CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END
                ), 0) AS saldo
                FROM homepet_{$baseId}.cliente_credito_movimento
                WHERE estabelecimento_id = :estab AND cliente_id = :cliente";
        $query = $this->conn->executeQuery($sql, [
            'estab'   => $baseId,
            'cliente' => $clienteId,
        ]);
        return $query->fetchAssociative();
    }

    public function inserir(
        int $baseId,
        int $clienteId,
        ?int $petId,
        string $tipo,
        float $valor,
        ?string $descricao,
        ?string $origem,
        ?int $origemId,
        ?int $usuarioId
    ): void {
        if ($valor <= 0 || !in_array($tipo, ['entrada', 'saida'], true)) {
            return;
        }

        $sql = "INSERT INTO homepet_{$baseId}.cliente_credito_movimento
                    (estabelecimento_id, cliente_id, pet_id, tipo, valor,
                     descricao, origem, origem_id, criado_em, criado_por)
                VALUES
                    (:estab, :cliente, :pet, :tipo, :valor,
                     :descricao, :origem, :origem_id, NOW(), :criado_por)";

        $this->conn->executeStatement($sql, [
            'estab'      => $baseId,
            'cliente'    => $clienteId,
            'pet'        => $petId,
            'tipo'       => $tipo,
            'valor'      => $valor,
            'descricao'  => $descricao,
            'origem'     => $origem,
            'origem_id'  => $origemId,
            'criado_por' => $usuarioId,
        ]);
    }

    public function historicoDoCliente(int $baseId, int $clienteId, int $limite = 20): array
    {
        $sql = "SELECT id, pet_id, tipo, valor, descricao, origem, origem_id, criado_em
                FROM homepet_{$baseId}.cliente_credito_movimento
                WHERE estabelecimento_id = :estab AND cliente_id = :cliente
                ORDER BY criado_em DESC, id DESC
                LIMIT :limite";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('estab', $baseId);
        $stmt->bindValue('cliente', $clienteId);
        $stmt->bindValue('limite', $limite, \PDO::PARAM_INT);
        return $stmt->executeQuery()->fetchAllAssociative();
    }
}
