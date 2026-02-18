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

    public function findBoxDisponivel(): ?array
    {
        $sql = "SELECT b.id, b.numero, b.ocupado
                FROM box b
                WHERE b.ocupado = 0
                ORDER BY b.numero ASC
                LIMIT 1";
        $result = $this->conn->fetchAssociative($sql);
        return $result ?: null;
    }

    public function findBoxesOcupados(): array
    {
        $sql = "SELECT b.id, b.numero, b.ocupado,
                       h.id AS hospedagem_id, h.data_entrada, h.data_saida,
                       p.id AS pet_id, p.nome AS pet_nome, p.especie,
                       c.id AS cliente_id, c.nome AS cliente_nome, c.telefone
                FROM box b
                LEFT JOIN hospedagem_caes h ON h.box_id = b.id AND h.data_saida >= NOW()
                LEFT JOIN pet p ON p.id = h.pet_id
                LEFT JOIN cliente c ON c.id = h.cliente_id
                WHERE b.ocupado = 1
                ORDER BY b.numero ASC";
        return $this->conn->fetchAllAssociative($sql);
    }

    public function findByNumero(int $numero): ?array
    {
        $sql = "SELECT id, numero, ocupado FROM box WHERE numero = :numero LIMIT 1";
        $result = $this->conn->fetchAssociative($sql, ['numero' => $numero]);
        return $result ?: null;
    }

    public function countBoxes(): int
    {
        return (int) $this->conn->fetchOne("SELECT COUNT(id) FROM box");
    }

    public function findAllComStatus(): array
    {
        $sql = "SELECT b.id, b.numero, b.ocupado,
                       p.nome AS pet_atual, c.nome AS cliente_atual
                FROM box b
                LEFT JOIN hospedagem_caes h ON h.box_id = b.id AND h.data_saida >= NOW()
                LEFT JOIN pet p ON p.id = h.pet_id
                LEFT JOIN cliente c ON c.id = h.cliente_id
                ORDER BY b.numero ASC";
        return $this->conn->fetchAllAssociative($sql);
    }

    public function atualizarOcupacao(int $boxId, bool $ocupado): void
    {
        $this->conn->executeQuery(
            "UPDATE box SET ocupado = :ocupado WHERE id = :id",
            ['ocupado' => (int) $ocupado, 'id' => $boxId]
        );
    }
}