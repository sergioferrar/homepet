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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Estoque::class);
    }

    /**
     * Salvar estoque
     */
    public function save(Estoque $estoque, bool $flush = false): void
    {
        $this->getEntityManager()->persist($estoque);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remover estoque
     */
    public function remove(Estoque $estoque, bool $flush = false): void
    {
        $this->getEntityManager()->remove($estoque);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Buscar estoque por produto
     */
    public function findByProduto(int $produtoId): ?Estoque
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.produtoId = :produtoId')
            ->setParameter('produtoId', $produtoId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Buscar todos os estoques de um estabelecimento
     */
    public function findByEstabelecimento(int $estabelecimentoId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.estabelecimentoId = :estabelecimentoId')
            ->andWhere('e.status = :status')
            ->setParameter('estabelecimentoId', $estabelecimentoId)
            ->setParameter('status', 'ativo')
            ->orderBy('e.quantidadeAtual', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Buscar produtos com estoque baixo
     */
    public function findEstoqueBaixo(int $estabelecimentoId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.estabelecimentoId = :estabelecimentoId')
            ->andWhere('e.quantidadeDisponivel < e.estoqueMinimo')
            ->andWhere('e.status = :status')
            ->setParameter('estabelecimentoId', $estabelecimentoId)
            ->setParameter('status', 'ativo')
            ->orderBy('e.quantidadeDisponivel', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Buscar produtos com estoque crítico (menos de 50% do mínimo)
     */
    public function findEstoqueCritico(int $estabelecimentoId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.estabelecimentoId = :estabelecimentoId')
            ->andWhere('e.quantidadeDisponivel < (e.estoqueMinimo * 0.5)')
            ->andWhere('e.status = :status')
            ->setParameter('estabelecimentoId', $estabelecimentoId)
            ->setParameter('status', 'ativo')
            ->orderBy('e.quantidadeDisponivel', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Buscar produtos refrigerados
     */
    public function findRefrigerados(int $estabelecimentoId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.estabelecimentoId = :estabelecimentoId')
            ->andWhere('e.refrigerado = 1')
            ->andWhere('e.status = :status')
            ->setParameter('estabelecimentoId', $estabelecimentoId)
            ->setParameter('status', 'ativo')
            ->getQuery()
            ->getResult();
    }

    /**
     * Buscar produtos que controlam lote
     */
    public function findControlaLote(int $estabelecimentoId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.estabelecimentoId = :estabelecimentoId')
            ->andWhere('e.controlaLote = 1')
            ->andWhere('e.status = :status')
            ->setParameter('estabelecimentoId', $estabelecimentoId)
            ->setParameter('status', 'ativo')
            ->getQuery()
            ->getResult();
    }

    /**
     * Buscar produtos que controlam validade
     */
    public function findControlaValidade(int $estabelecimentoId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.estabelecimentoId = :estabelecimentoId')
            ->andWhere('e.controlaValidade = 1')
            ->andWhere('e.status = :status')
            ->setParameter('estabelecimentoId', $estabelecimentoId)
            ->setParameter('status', 'ativo')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcular valor total do estoque
     */
    public function calcularValorTotalEstoque(int $estabelecimentoId): float
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.quantidadeAtual * e.custoMedio) as valorTotal')
            ->andWhere('e.estabelecimentoId = :estabelecimentoId')
            ->andWhere('e.status = :status')
            ->setParameter('estabelecimentoId', $estabelecimentoId)
            ->setParameter('status', 'ativo')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Contar produtos com estoque zerado
     */
    public function countEstoqueZerado(int $estabelecimentoId): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.estabelecimentoId = :estabelecimentoId')
            ->andWhere('e.quantidadeAtual = 0')
            ->andWhere('e.status = :status')
            ->setParameter('estabelecimentoId', $estabelecimentoId)
            ->setParameter('status', 'ativo')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Buscar estoques por local
     */
    public function findByLocal(string $localEstoqueId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.localEstoqueId = :localEstoqueId')
            ->andWhere('e.status = :status')
            ->setParameter('localEstoqueId', $localEstoqueId)
            ->setParameter('status', 'ativo')
            ->getQuery()
            ->getResult();
    }

    /**
     * Relatório de estoque com filtros avançados
     */
    public function findComFiltros(array $filtros): array
    {
        $qb = $this->createQueryBuilder('e');

        if (!empty($filtros['estabelecimento_id'])) {
            $qb->andWhere('e.estabelecimentoId = :estabelecimentoId')
               ->setParameter('estabelecimentoId', $filtros['estabelecimento_id']);
        }

        if (!empty($filtros['status'])) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', $filtros['status']);
        }

        if (!empty($filtros['refrigerado'])) {
            $qb->andWhere('e.refrigerado = :refrigerado')
               ->setParameter('refrigerado', $filtros['refrigerado']);
        }

        if (isset($filtros['estoque_baixo']) && $filtros['estoque_baixo']) {
            $qb->andWhere('e.quantidadeDisponivel < e.estoqueMinimo');
        }

        if (isset($filtros['estoque_zerado']) && $filtros['estoque_zerado']) {
            $qb->andWhere('e.quantidadeAtual = 0');
        }

        return $qb->orderBy('e.quantidadeAtual', 'ASC')
                  ->getQuery()
                  ->getResult();
    }
}