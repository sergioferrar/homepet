<?php

namespace App\Repository;

use App\Entity\Produto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProdutoRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produto::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function save(Produto $produto, bool $flush = true): void
    {
        $this->_em->persist($produto);
        if ($flush) { $this->_em->flush(); }
    }

    public function remove(Produto $produto, bool $flush = true): void
    {
        $this->_em->remove($produto);
        if ($flush) { $this->_em->flush(); }
    }

    /**
     * Busca produtos de um estabelecimento com dados de estoque via LEFT JOIN
     */
    public function findByEstabelecimento(int $estabelecimentoId): array
    {
        $sql = "SELECT p.id, p.nome, p.descricao, p.preco_venda, p.codigo_barras,
                       p.unidade_medida, p.categoria, p.status, p.estabelecimento_id,
                       COALESCE(e.quantidade_atual, 0) AS estoque_atual,
                       COALESCE(e.quantidade_disponivel, 0) AS estoque_disponivel,
                       COALESCE(e.estoque_minimo, 0) AS estoque_minimo,
                       e.status AS estoque_status
                FROM produto p
                LEFT JOIN estoque e ON e.produto_id = p.id AND e.estabelecimento_id = p.estabelecimento_id
                WHERE p.estabelecimento_id = :id
                ORDER BY p.nome ASC";

        return $this->conn->fetchAllAssociative($sql, ['id' => $estabelecimentoId]);
    }

    /**
     * Busca produto por ID com dados de estoque
     */
    public function findByIdComEstoque(int $produtoId, int $estabelecimentoId): ?array
    {
        $sql = "SELECT p.id, p.nome, p.descricao, p.preco_venda, p.preco_custo,
                       p.codigo_barras, p.unidade_medida, p.categoria, p.status,
                       COALESCE(e.quantidade_atual, 0) AS estoque_atual,
                       COALESCE(e.quantidade_disponivel, 0) AS estoque_disponivel,
                       COALESCE(e.estoque_minimo, 0) AS estoque_minimo,
                       COALESCE(e.custo_medio, 0) AS custo_medio
                FROM produto p
                LEFT JOIN estoque e ON e.produto_id = p.id AND e.estabelecimento_id = :estab
                WHERE p.id = :id AND p.estabelecimento_id = :estab
                LIMIT 1";

        $result = $this->conn->fetchAssociative($sql, [
            'id'    => $produtoId,
            'estab' => $estabelecimentoId,
        ]);
        return $result ?: null;
    }

    /**
     * Busca produtos ativos para PDV com estoque disponível
     */
    public function findAtivosComEstoque(int $estabelecimentoId): array
    {
        $sql = "SELECT p.id, p.nome, p.preco_venda, p.codigo_barras,
                       p.unidade_medida, p.categoria,
                       COALESCE(e.quantidade_disponivel, 0) AS estoque_disponivel
                FROM produto p
                LEFT JOIN estoque e ON e.produto_id = p.id AND e.estabelecimento_id = p.estabelecimento_id
                WHERE p.estabelecimento_id = :estab
                  AND p.status = 'ativo'
                  AND COALESCE(e.quantidade_disponivel, 0) > 0
                ORDER BY p.nome ASC";

        return $this->conn->fetchAllAssociative($sql, ['estab' => $estabelecimentoId]);
    }

    /**
     * Busca por nome ou código de barras (PDV)
     */
    public function buscarParaPDV(int $estabelecimentoId, string $termo): array
    {
        $sql = "SELECT p.id, p.nome, p.preco_venda, p.codigo_barras, p.unidade_medida,
                       COALESCE(e.quantidade_disponivel, 0) AS estoque_disponivel
                FROM produto p
                LEFT JOIN estoque e ON e.produto_id = p.id AND e.estabelecimento_id = p.estabelecimento_id
                WHERE p.estabelecimento_id = :estab
                  AND p.status = 'ativo'
                  AND (p.nome LIKE :termo OR p.codigo_barras = :codigoExato)
                ORDER BY p.nome ASC
                LIMIT 20";

        return $this->conn->fetchAllAssociative($sql, [
            'estab'       => $estabelecimentoId,
            'termo'       => '%' . $termo . '%',
            'codigoExato' => $termo,
        ]);
    }

    /**
     * Produtos mais vendidos no período com LEFT JOIN em venda_item
     */
    public function findMaisVendidos(int $estabelecimentoId, \DateTime $inicio, \DateTime $fim, int $limite = 10): array
    {
        $sql = "SELECT p.id, p.nome, p.categoria,
                       COUNT(vi.id) AS total_pedidos,
                       COALESCE(SUM(vi.quantidade), 0) AS total_vendido,
                       COALESCE(SUM(vi.subtotal), 0) AS total_valor
                FROM produto p
                LEFT JOIN venda_item vi ON vi.produto_id = p.id
                LEFT JOIN venda v ON v.id = vi.venda_id
                          AND v.estabelecimento_id = :estab
                          AND DATE(v.data) BETWEEN :inicio AND :fim
                          AND v.status NOT IN ('Carrinho', 'Cancelada')
                WHERE p.estabelecimento_id = :estab
                GROUP BY p.id, p.nome, p.categoria
                ORDER BY total_vendido DESC
                LIMIT :limite";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('estab',  $estabelecimentoId);
        $stmt->bindValue('inicio', $inicio->format('Y-m-d'));
        $stmt->bindValue('fim',    $fim->format('Y-m-d'));
        $stmt->bindValue('limite', $limite, \PDO::PARAM_INT);
        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * Produtos por categoria com contagem de estoque
     */
    public function findPorCategoria(int $estabelecimentoId): array
    {
        $sql = "SELECT p.categoria,
                       COUNT(p.id) AS total_produtos,
                       SUM(CASE WHEN COALESCE(e.quantidade_atual, 0) = 0 THEN 1 ELSE 0 END) AS sem_estoque
                FROM produto p
                LEFT JOIN estoque e ON e.produto_id = p.id AND e.estabelecimento_id = p.estabelecimento_id
                WHERE p.estabelecimento_id = :estab
                  AND p.status = 'ativo'
                GROUP BY p.categoria
                ORDER BY p.categoria ASC";

        return $this->conn->fetchAllAssociative($sql, ['estab' => $estabelecimentoId]);
    }

/** Lista enxuta de produtos para o catálogo do PDV. */
    public function listarParaPdv(int $estabelecimentoId): array
    {
        return $this->conn->fetchAllAssociative(
            "SELECT id, nome, preco_venda AS valor, estoque_atual AS estoque
             FROM homepet_{$estabelecimentoId}.produto
             WHERE estabelecimento_id = :estab",
            ['estab' => $estabelecimentoId]
        );
    }

    /** Lista produtos comissionáveis para a tela de configuração de comissões. */
    public function listarParaComissao(int $estabelecimentoId): array
    {
        return $this->conn->fetchAllAssociative(
            "SELECT id, nome, categoria, preco_venda, comissao_percentual
             FROM homepet_{$estabelecimentoId}.produto
             WHERE estabelecimento_id = :estab
             ORDER BY categoria, nome",
            ['estab' => $estabelecimentoId]
        );
    }

    /** Busca dados atuais do produto antes de registrar uma venda. */
    public function buscarDadosVenda(int $estabelecimentoId, int $produtoId): ?array
    {
        $row = $this->conn->fetchAssociative(
            'SELECT id, nome, preco_venda, estoque_atual, comissao_percentual
             FROM produto WHERE id = :id AND estabelecimento_id = :estab',
            ['id' => $produtoId, 'estab' => $estabelecimentoId]
        );

        return $row ?: null;
    }

    /** Atualiza estoque e registra a saída no mesmo repositório de produto. */
    public function registrarBaixaPorVenda(
        int $estabelecimentoId,
        int $produtoId,
        int $quantidade,
        int $vendaId,
        string $responsavel
    ): void {
        $produto = $this->buscarDadosVenda($estabelecimentoId, $produtoId);
        if (!$produto) {
            return;
        }

        $estoqueAnterior = (int) ($produto['estoque_atual'] ?? 0);
        $novoEstoque = max(0, $estoqueAnterior - $quantidade);

        $this->conn->executeStatement(
            'UPDATE produto SET estoque_atual = :novo
             WHERE id = :id AND estabelecimento_id = :estab',
            ['novo' => $novoEstoque, 'id' => $produtoId, 'estab' => $estabelecimentoId]
        );

        $this->conn->executeStatement(
            'INSERT INTO estoque_movimento
                (produto_id, estabelecimento_id, quantidade, tipo, origem, data, observacao)
             VALUES (:pid, :estab, :qtd, :tipo, :origem, :data, :obs)',
            [
                'pid' => $produtoId,
                'estab' => $estabelecimentoId,
                'qtd' => $quantidade,
                'tipo' => 'SAIDA',
                'origem' => 'Venda Clínica #' . $vendaId,
                'data' => (new \DateTime())->format('Y-m-d H:i:s'),
                'obs' => sprintf(
                    'Venda para: %s | Estoque anterior: %d | Novo estoque: %d',
                    $responsavel,
                    $estoqueAnterior,
                    $novoEstoque
                ),
            ]
        );
    }

    /** Salva o percentual de comissão de um produto. */
    public function atualizarComissao(int $estabelecimentoId, int $produtoId, ?float $percentual): int
    {
        return $this->conn->executeStatement(
            "UPDATE homepet_{$estabelecimentoId}.produto
             SET comissao_percentual = :pct
             WHERE id = :id AND estabelecimento_id = :estab",
            ['pct' => $percentual, 'id' => $produtoId, 'estab' => $estabelecimentoId]
        );
    }

/** Produtos ativos no catálogo de venda da ficha clínica. */
    public function listarDisponiveisParaVenda(int $estabelecimentoId): array
    {
        return $this->conn->fetchAllAssociative(
            'SELECT id, nome, preco_venda, COALESCE(estoque_atual, 0) AS estoque_atual
             FROM produto
             WHERE estabelecimento_id = :estabelecimento
               AND preco_venda IS NOT NULL
             ORDER BY nome ASC',
            ['estabelecimento' => $estabelecimentoId]
        );
    }
}