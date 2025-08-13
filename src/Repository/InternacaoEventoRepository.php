<?php

namespace App\Repository;

use App\Entity\InternacaoEvento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InternacaoEvento>
 *
 * @method InternacaoEvento|null find($id, $lockMode = null, $lockVersion = null)
 * @method InternacaoEvento|null findOneBy(array $criteria, array $orderBy = null)
 * @method InternacaoEvento[]    findAll()
 * @method InternacaoEvento[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InternacaoEventoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InternacaoEvento::class);
    }

    public function add(InternacaoEvento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(InternacaoEvento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return InternacaoEvento[] Returns an array of InternacaoEvento objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('i.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?InternacaoEvento
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
