<?php

namespace App\Repository;

use App\Entity\NotaFiscal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotaFiscal>
 */
class NotaFiscalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotaFiscal::class);
    }

    /** @return NotaFiscal[] */
    public function findByEstabelecimento(int $eid, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.estabelecimentoId = :eid')
            ->setParameter('eid', $eid)
            ->orderBy('n.criadoEm', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return NotaFiscal[] */
    public function findByVenda(int $vendaId): array
    {
        return $this->findBy(['vendaId' => $vendaId], ['criadoEm' => 'DESC']);
    }

    public function findByAsaasId(string $asaasInvoiceId): ?NotaFiscal
    {
        return $this->findOneBy(['asaasInvoiceId' => $asaasInvoiceId]);
    }

    /** Conta notas emitidas (AUTHORIZED) no mês vigente */
    public function countAutorizadasNoMes(int $eid): int
    {
        $inicio = new \DateTime('first day of this month midnight');
        $fim    = new \DateTime('last day of this month 23:59:59');

        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.estabelecimentoId = :eid')
            ->andWhere('n.status = :status')
            ->andWhere('n.dataEmissao BETWEEN :inicio AND :fim')
            ->setParameter('eid', $eid)
            ->setParameter('status', NotaFiscal::STATUS_AUTORIZADA)
            ->setParameter('inicio', $inicio)
            ->setParameter('fim', $fim)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function add(NotaFiscal $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }

    public function remove(NotaFiscal $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }
}
