<?php

namespace App\Repository;

use App\Entity\VendaItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VendaItem>
 */
class VendaItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VendaItem::class);
    }

    /**
     * Retorna todos os itens de uma venda específica.
     */
    public function findByVendaId(int $vendaId): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.venda = :id')
            ->setParameter('id', $vendaId)
            ->orderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retorna os produtos mais vendidos em determinado período.
     */
    public function maisVendidos(int $estabelecimentoId, \DateTime $inicio, \DateTime $fim): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT produto, SUM(quantidade) AS total_vendido, SUM(subtotal) AS total_valor
            FROM venda_item vi
            JOIN venda v ON v.id = vi.venda_id
            WHERE v.estabelecimento_id = :baseId
              AND v.data BETWEEN :inicio AND :fim
            GROUP BY produto
            ORDER BY total_vendido DESC
        ";

        return $conn->fetchAllAssociative($sql, [
            'baseId' => $estabelecimentoId,
            'inicio' => $inicio->format('Y-m-d 00:00:00'),
            'fim'    => $fim->format('Y-m-d 23:59:59'),
        ]);
    }
}
