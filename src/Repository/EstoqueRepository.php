<?php

namespace App\Repository;

use App\Entity\Estoque;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Estoque>
 *
 * @method Estoque|null find($id, $lockMode = null, $lockVersion = null)
 * @method Estoque|null findOneBy(array $criteria, array $orderBy = null)
 * @method Estoque[]    findAll()
 * @method Estoque[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EstoqueRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Estoque::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    /**
     * Persiste um novo estoque
     */
    public function save(Estoque $estoque, bool $flush = false): void
    {
        $this->getEntityManager()->persist($estoque);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove um estoque
     */
    public function remove(Estoque $estoque, bool $flush = false): void
    {
        $this->getEntityManager()->remove($estoque);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Busca estoque por produto com dados do produto via LEFT JOIN
     */
    public function findByProduto(int $produtoId): ?array
    {
        $sql = "SELECT e.id, e.produto_id, e.estabelecimento_id, e.quantidade_atual,
                       e.quantidade_disponivel, e.estoque_minimo, e.custo_medio,
                       e.refrigerado, e.controla_lote, e.controla_validade,
                       e.local_estoque_id, e.status,
                       p.nome AS produto_nome, p.descricao AS produto_descricao,
                       p.preco_venda, p.codigo_barras, p.categoria
                FROM estoque e
                LEFT JOIN produto p ON p.id = e.produto_id
                WHERE e.produto_id = :produtoId
                LIMIT 1";

        $result = $this->conn->fetchAssociative($sql, ['produtoId' => $produtoId]);
        return $result ?: null;
    }

    /**
     * Busca todos os estoques ativos de um estabelecimento com dados do produto
     */
    public function findByEstabelecimento(int $estabelecimentoId): array
    {
        $sql = "SELECT e.id, e.produto_id, e.quantidade_atual, e.quantidade_disponivel,
                       e.estoque_minimo, e.custo_medio, e.refrigerado, e.controla_lote,
                       e.controla_validade, e.local_estoque_id, e.status,
                       p.nome AS produto_nome, p.descricao AS produto_descricao,
                       p.preco_venda, p.codigo_barras, p.categoria
                FROM estoque e
                LEFT JOIN produto p ON p.id = e.produto_id
                WHERE e.estabelecimento_id = :estabelecimentoId
                  AND e.status = 'ativo'
                ORDER BY e.quantidade_atual ASC";

        return $this->conn->fetchAllAssociative($sql, [
            'estabelecimentoId' => $estabelecimentoId,
        ]);
    }

    /**
     * Busca produtos com estoque abaixo do mínimo
     */
    public function findEstoqueBaixo(int $estabelecimentoId): array
    {
        $sql = "SELECT e.id, e.produto_id, e.quantidade_atual, e.quantidade_disponivel,
                       e.estoque_minimo, e.custo_medio, e.status,
                       p.nome AS produto_nome, p.codigo_barras, p.categoria
                FROM estoque e
                LEFT JOIN produto p ON p.id = e.produto_id
                WHERE e.estabelecimento_id = :estabelecimentoId
                  AND e.quantidade_disponivel < e.estoque_minimo
                  AND e.status = 'ativo'
                ORDER BY e.quantidade_disponivel ASC";

        return $this->conn->fetchAllAssociative($sql, [
            'estabelecimentoId' => $estabelecimentoId,
        ]);
    }

    /**
     * Busca produtos com estoque crítico (menos de 50% do mínimo)
     */
    public function findEstoqueCritico(int $estabelecimentoId): array
    {
        $sql = "SELECT e.id, e.produto_id, e.quantidade_atual, e.quantidade_disponivel,
                       e.estoque_minimo, e.custo_medio, e.status,
                       p.nome AS produto_nome, p.codigo_barras, p.categoria
                FROM estoque e
                LEFT JOIN produto p ON p.id = e.produto_id
                WHERE e.estabelecimento_id = :estabelecimentoId
                  AND e.quantidade_disponivel < (e.estoque_minimo * 0.5)
                  AND e.status = 'ativo'
                ORDER BY e.quantidade_disponivel ASC";

        return $this->conn->fetchAllAssociative($sql, [
            'estabelecimentoId' => $estabelecimentoId,
        ]);
    }

    /**
     * Busca produtos refrigerados com dados do produto
     */
    public function findRefrigerados(int $estabelecimentoId): array
    {
        $sql = "SELECT e.id, e.produto_id, e.quantidade_atual, e.quantidade_disponivel,
                       e.estoque_minimo, e.custo_medio, e.refrigerado, e.status,
                       p.nome AS produto_nome, p.codigo_barras, p.categoria
                FROM estoque e
                LEFT JOIN produto p ON p.id = e.produto_id
                WHERE e.estabelecimento_id = :estabelecimentoId
                  AND e.refrigerado = 1
                  AND e.status = 'ativo'
                ORDER BY p.nome ASC";

        return $this->conn->fetchAllAssociative($sql, [
            'estabelecimentoId' => $estabelecimentoId,
        ]);
    }

    /**
     * Busca produtos que controlam lote
     */
    public function findControlaLote(int $estabelecimentoId): array
    {
        $sql = "SELECT e.id, e.produto_id, e.quantidade_atual, e.quantidade_disponivel,
                       e.estoque_minimo, e.controla_lote, e.status,
                       p.nome AS produto_nome, p.codigo_barras, p.categoria
                FROM estoque e
                LEFT JOIN produto p ON p.id = e.produto_id
                WHERE e.estabelecimento_id = :estabelecimentoId
                  AND e.controla_lote = 1
                  AND e.status = 'ativo'
                ORDER BY p.nome ASC";

        return $this->conn->fetchAllAssociative($sql, [
            'estabelecimentoId' => $estabelecimentoId,
        ]);
    }

    /**
     * Busca produtos que controlam validade
     */
    public function findControlaValidade(int $estabelecimentoId): array
    {
        $sql = "SELECT e.id, e.produto_id, e.quantidade_atual, e.quantidade_disponivel,
                       e.estoque_minimo, e.controla_validade, e.status,
                       p.nome AS produto_nome, p.codigo_barras, p.categoria
                FROM estoque e
                LEFT JOIN produto p ON p.id = e.produto_id
                WHERE e.estabelecimento_id = :estabelecimentoId
                  AND e.controla_validade = 1
                  AND e.status = 'ativo'
                ORDER BY p.nome ASC";

        return $this->conn->fetchAllAssociative($sql, [
            'estabelecimentoId' => $estabelecimentoId,
        ]);
    }

    /**
     * Calcula valor total do estoque de um estabelecimento
     */
    public function calcularValorTotalEstoque(int $estabelecimentoId): float
    {
        $sql = "SELECT COALESCE(SUM(e.quantidade_atual * e.custo_medio), 0) AS valor_total
                FROM estoque e
                WHERE e.estabelecimento_id = :estabelecimentoId
                  AND e.status = 'ativo'";

        return (float) $this->conn->fetchOne($sql, [
            'estabelecimentoId' => $estabelecimentoId,
        ]);
    }

    /**
     * Conta produtos com estoque zerado
     */
    public function countEstoqueZerado(int $estabelecimentoId): int
    {
        $sql = "SELECT COUNT(e.id) AS total
                FROM estoque e
                WHERE e.estabelecimento_id = :estabelecimentoId
                  AND e.quantidade_atual = 0
                  AND e.status = 'ativo'";

        return (int) $this->conn->fetchOne($sql, [
            'estabelecimentoId' => $estabelecimentoId,
        ]);
    }

    /**
     * Busca estoques por local de armazenamento
     */
    public function findByLocal(string $localEstoqueId): array
    {
        $sql = "SELECT e.id, e.produto_id, e.quantidade_atual, e.quantidade_disponivel,
                       e.estoque_minimo, e.local_estoque_id, e.status,
                       p.nome AS produto_nome, p.codigo_barras, p.categoria
                FROM estoque e
                LEFT JOIN produto p ON p.id = e.produto_id
                WHERE e.local_estoque_id = :localEstoqueId
                  AND e.status = 'ativo'
                ORDER BY p.nome ASC";

        return $this->conn->fetchAllAssociative($sql, [
            'localEstoqueId' => $localEstoqueId,
        ]);
    }

    /**
     * Relatório de estoque com filtros avançados — SQL puro, sem hydration de entidades
     */
    public function findComFiltros(array $filtros): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (!empty($filtros['estabelecimento_id'])) {
            $where[]  = 'e.estabelecimento_id = :estabelecimentoId';
            $params['estabelecimentoId'] = $filtros['estabelecimento_id'];
        }

        if (!empty($filtros['status'])) {
            $where[]  = 'e.status = :status';
            $params['status'] = $filtros['status'];
        }

        if (!empty($filtros['refrigerado'])) {
            $where[]  = 'e.refrigerado = :refrigerado';
            $params['refrigerado'] = $filtros['refrigerado'];
        }

        if (!empty($filtros['estoque_baixo'])) {
            $where[] = 'e.quantidade_disponivel < e.estoque_minimo';
        }

        if (!empty($filtros['estoque_zerado'])) {
            $where[] = 'e.quantidade_atual = 0';
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT e.id, e.produto_id, e.quantidade_atual, e.quantidade_disponivel,
                       e.estoque_minimo, e.custo_medio, e.refrigerado, e.controla_lote,
                       e.controla_validade, e.local_estoque_id, e.status,
                       p.nome AS produto_nome, p.descricao AS produto_descricao,
                       p.preco_venda, p.codigo_barras, p.categoria
                FROM estoque e
                LEFT JOIN produto p ON p.id = e.produto_id
                WHERE {$whereClause}
                ORDER BY e.quantidade_atual ASC";

        return $this->conn->fetchAllAssociative($sql, $params);
    }
}