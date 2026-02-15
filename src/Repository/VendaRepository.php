<?php

namespace App\Repository;

use App\Entity\Venda;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repositório de Vendas com isolamento por tenant
 * 
 * @extends ServiceEntityRepository<Venda>
 * @method Venda|null find($id, $lockMode = null, $lockVersion = null)
 * @method Venda|null findOneBy(array $criteria, array $orderBy = null)
 * @method Venda[]    findAll()
 * @method Venda[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VendaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Venda::class);
    }

    /**
     * Busca vendas por data com isolamento de tenant
     */
    public function findByData(int $estabelecimentoId, \DateTime $data): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.estabelecimentoId = :estab')
            ->andWhere('DATE(v.data) = :data')
            ->setParameter('estab', $estabelecimentoId)
            ->setParameter('data', $data->format('Y-m-d'))
            ->orderBy('v.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retorna total por forma de pagamento no dia
     */
    public function totalPorFormaPagamento(int $estabelecimentoId, \DateTime $data): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT 
                metodo_pagamento AS metodo,
                COALESCE(SUM(total), 0) AS total
            FROM venda
            WHERE estabelecimento_id = :baseId
              AND DATE(data) = :data
              AND status NOT IN ('Carrinho', 'Cancelada')
            GROUP BY metodo_pagamento
            ORDER BY metodo_pagamento ASC
        ";

        return $conn->fetchAllAssociative($sql, [
            'baseId' => $estabelecimentoId,
            'data' => $data->format('Y-m-d'),
        ]);
    }

    /**
     * Total geral do dia
     */
    public function totalGeralDoDia(int $estabelecimentoId, \DateTime $data): float
    {
        $result = $this->createQueryBuilder('v')
            ->select('COALESCE(SUM(v.total), 0) as total')
            ->where('v.estabelecimentoId = :estab')
            ->andWhere('DATE(v.data) = :data')
            ->andWhere('v.status NOT IN (:statusExcluidos)')
            ->setParameter('estab', $estabelecimentoId)
            ->setParameter('data', $data->format('Y-m-d'))
            ->setParameter('statusExcluidos', ['Carrinho', 'Cancelada'])
            ->getQuery()
            ->getSingleScalarResult();

        return (float)$result;
    }

    /**
     * Vendas por período
     */
    public function findByPeriodo(int $estabelecimentoId, \DateTime $inicio, \DateTime $fim): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.estabelecimentoId = :estab')
            ->andWhere('DATE(v.data) BETWEEN :inicio AND :fim')
            ->setParameter('estab', $estabelecimentoId)
            ->setParameter('inicio', $inicio->format('Y-m-d'))
            ->setParameter('fim', $fim->format('Y-m-d'))
            ->orderBy('v.data', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Total por período
     */
    public function totalPorPeriodo(int $estabelecimentoId, \DateTime $inicio, \DateTime $fim): float
    {
        $result = $this->createQueryBuilder('v')
            ->select('COALESCE(SUM(v.total), 0) as total')
            ->where('v.estabelecimentoId = :estab')
            ->andWhere('DATE(v.data) BETWEEN :inicio AND :fim')
            ->andWhere('v.status NOT IN (:statusExcluidos)')
            ->setParameter('estab', $estabelecimentoId)
            ->setParameter('inicio', $inicio->format('Y-m-d'))
            ->setParameter('fim', $fim->format('Y-m-d'))
            ->setParameter('statusExcluidos', ['Carrinho', 'Cancelada'])
            ->getQuery()
            ->getSingleScalarResult();

        return (float)$result;
    }

    /**
     * Busca vendas em carrinho aguardando finalização
     */
    public function findCarrinho(int $estabelecimentoId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT v.id, v.pet_id, v.total, v.data, v.origem, v.observacao,
                   p.nome AS pet_nome, c.nome AS cliente_nome
            FROM venda v
            LEFT JOIN pet p ON v.pet_id = p.id
            LEFT JOIN cliente c ON p.dono_id = c.id
            WHERE v.estabelecimento_id = :baseId
              AND v.status = 'Carrinho'
            ORDER BY v.data DESC
        ";

        return $conn->fetchAllAssociative($sql, [
            'baseId' => $estabelecimentoId,
        ]);
    }

    /**
     * Inativa uma venda (cancelamento)
     */
    public function inativar(int $estabelecimentoId, int $vendaId): bool
    {
        $qb = $this->createQueryBuilder('v')
            ->update()
            ->set('v.status', ':status')
            ->where('v.id = :id')
            ->andWhere('v.estabelecimentoId = :estab')
            ->setParameter('status', 'Cancelada')
            ->setParameter('id', $vendaId)
            ->setParameter('estab', $estabelecimentoId);

        return $qb->getQuery()->execute() > 0;
    }

    /**
     * Finaliza venda do carrinho
     */
    public function finalizarVenda(
        int $estabelecimentoId,
        int $vendaId,
        string $metodoPagamento,
        ?string $bandeiraCartao = null,
        ?int $parcelas = null
    ): bool {
        $status = ($metodoPagamento === 'pendente') ? 'Pendente' : 'Aberta';

        $qb = $this->createQueryBuilder('v')
            ->update()
            ->set('v.status', ':status')
            ->set('v.metodoPagamento', ':metodo')
            ->set('v.data', ':data')
            ->where('v.id = :id')
            ->andWhere('v.estabelecimentoId = :estab')
            ->andWhere('v.status = :statusAtual')
            ->setParameter('status', $status)
            ->setParameter('metodo', $metodoPagamento)
            ->setParameter('data', new \DateTime())
            ->setParameter('id', $vendaId)
            ->setParameter('estab', $estabelecimentoId)
            ->setParameter('statusAtual', 'Carrinho');

        if ($bandeiraCartao !== null) {
            $qb->set('v.bandeiraCartao', ':bandeira')
               ->setParameter('bandeira', $bandeiraCartao);
        }

        if ($parcelas !== null) {
            $qb->set('v.parcelas', ':parcelas')
               ->setParameter('parcelas', $parcelas);
        }

        return $qb->getQuery()->execute() > 0;
    }

    /**
     * Busca vendas de um pet específico
     */
    public function findByPet(int $estabelecimentoId, int $petId): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.estabelecimentoId = :estab')
            ->andWhere('v.petId = :petId')
            ->andWhere('v.status NOT IN (:statusExcluidos)')
            ->setParameter('estab', $estabelecimentoId)
            ->setParameter('petId', $petId)
            ->setParameter('statusExcluidos', ['Carrinho', 'Cancelada'])
            ->orderBy('v.data', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Resumo de vendas do dia
     */
    public function getResumoDoDia(int $estabelecimentoId, \DateTime $data): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT 
                COUNT(*) as quantidade,
                COALESCE(SUM(total), 0) as total
            FROM venda
            WHERE estabelecimento_id = :baseId
              AND DATE(data) = :data
              AND status NOT IN ('Carrinho', 'Cancelada')
        ";

        $result = $conn->fetchAssociative($sql, [
            'baseId' => $estabelecimentoId,
            'data' => $data->format('Y-m-d'),
        ]);

        $quantidade = (int)($result['quantidade'] ?? 0);
        $total = (float)($result['total'] ?? 0);

        return [
            'quantidade_vendas' => $quantidade,
            'total_vendas' => $total,
            'ticket_medio' => $quantidade > 0 ? $total / $quantidade : 0
        ];
    }
}
