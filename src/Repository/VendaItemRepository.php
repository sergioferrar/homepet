<?php

namespace App\Repository;

use App\Entity\VendaItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VendaItemRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VendaItem::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    /**
     * Itens de uma venda com dados do produto via LEFT JOIN
     */
    public function findByVendaId(int $vendaId): array
    {
        $sql = "SELECT vi.id, vi.venda_id, vi.produto_id, vi.produto AS produto_nome_snapshot,
                       vi.quantidade, vi.preco_unitario, vi.subtotal,
                       p.nome AS produto_nome, p.codigo_barras, p.unidade_medida, p.categoria
                FROM venda_item vi
                LEFT JOIN produto p ON p.id = vi.produto_id
                WHERE vi.venda_id = :vendaId
                ORDER BY vi.id ASC";

        return $this->conn->fetchAllAssociative($sql, ['vendaId' => $vendaId]);
    }

    /**
     * Produtos mais vendidos no período
     */
    public function maisVendidos(int $estabelecimentoId, \DateTime $inicio, \DateTime $fim): array
    {
        $sql = "SELECT vi.produto AS produto,
                       vi.produto_id,
                       p.codigo_barras, p.categoria,
                       SUM(vi.quantidade) AS total_vendido,
                       SUM(vi.subtotal) AS total_valor,
                       COUNT(DISTINCT v.id) AS total_pedidos
                FROM venda_item vi
                LEFT JOIN venda v ON v.id = vi.venda_id
                LEFT JOIN produto p ON p.id = vi.produto_id
                WHERE v.estabelecimento_id = :baseId
                  AND DATE(v.data) BETWEEN :inicio AND :fim
                  AND v.status NOT IN ('Carrinho', 'Cancelada')
                GROUP BY vi.produto_id, vi.produto, p.codigo_barras, p.categoria
                ORDER BY total_vendido DESC
                LIMIT 20";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $estabelecimentoId,
            'inicio' => $inicio->format('Y-m-d'),
            'fim'    => $fim->format('Y-m-d'),
        ]);
    }

    /**
     * Resumo de itens vendidos por categoria no período
     */
    public function resumoPorCategoria(int $estabelecimentoId, \DateTime $inicio, \DateTime $fim): array
    {
        $sql = "SELECT COALESCE(p.categoria, 'Sem categoria') AS categoria,
                       COUNT(vi.id) AS total_itens,
                       SUM(vi.quantidade) AS quantidade_total,
                       SUM(vi.subtotal) AS valor_total
                FROM venda_item vi
                LEFT JOIN venda v ON v.id = vi.venda_id
                LEFT JOIN produto p ON p.id = vi.produto_id
                WHERE v.estabelecimento_id = :baseId
                  AND DATE(v.data) BETWEEN :inicio AND :fim
                  AND v.status NOT IN ('Carrinho', 'Cancelada')
                GROUP BY p.categoria
                ORDER BY valor_total DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $estabelecimentoId,
            'inicio' => $inicio->format('Y-m-d'),
            'fim'    => $fim->format('Y-m-d'),
        ]);
    }

    /**
     * Insere item na venda
     */
    public function inserir(int $vendaId, int $produtoId, string $produtoNome, float $quantidade, float $precoUnitario): int
    {
        $sql = "INSERT INTO venda_item (venda_id, produto_id, produto, quantidade, preco_unitario, subtotal)
                VALUES (:venda_id, :produto_id, :produto, :quantidade, :preco_unitario, :subtotal)";

        $this->conn->executeQuery($sql, [
            'venda_id'       => $vendaId,
            'produto_id'     => $produtoId,
            'produto'        => $produtoNome,
            'quantidade'     => $quantidade,
            'preco_unitario' => $precoUnitario,
            'subtotal'       => $quantidade * $precoUnitario,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Remove todos os itens de uma venda
     */
    public function deleteByVendaId(int $vendaId): void
    {
        $this->conn->executeQuery(
            "DELETE FROM venda_item WHERE venda_id = :vendaId",
            ['vendaId' => $vendaId]
        );
    }
}