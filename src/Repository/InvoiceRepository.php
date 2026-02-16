<?php

namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function add(Invoice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Invoice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByEstabelecimento(int $estabelecimentoId)
    {
        return $this->createQueryBuilder('i')
            ->where('i.estabelecimentoId = :estabelecimentoId')
            ->setParameter('estabelecimentoId', $estabelecimentoId)
            ->orderBy('i.dataEmissao', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingInvoices()
    {
        return $this->createQueryBuilder('i')
            ->where('i.status = :status')
            ->setParameter('status', 'pendente')
            ->andWhere('i.dataVencimento < :hoje')
            ->setParameter('hoje', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    public function getTotalReceitaMes(\DateTime $inicio, \DateTime $fim)
    {
        return $this->createQueryBuilder('i')
            ->select('SUM(i.valorTotal - COALESCE(i.valorDesconto, 0)) as total')
            ->where('i.status = :status')
            ->andWhere('i.dataPagamento BETWEEN :inicio AND :fim')
            ->setParameter('status', 'pago')
            ->setParameter('inicio', $inicio)
            ->setParameter('fim', $fim)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getInvoicesPorStatus()
    {
        return $this->createQueryBuilder('i')
            ->select('i.status, COUNT(i.id) as quantidade, SUM(i.valorTotal - COALESCE(i.valorDesconto, 0)) as total')
            ->groupBy('i.status')
            ->getQuery()
            ->getResult();
    }
}
