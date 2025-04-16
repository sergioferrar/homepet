<?php

namespace App\Repository;

use App\Entity\Plano;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plano>
 *
 * @method Plano|null find($id, $lockMode = null, $lockVersion = null)
 * @method Plano|null findOneBy(array $criteria, array $orderBy = null)
 * @method Plano[]    findAll()
 * @method Plano[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlanoRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plano::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function add(Plano $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Plano $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function createPlan($data){
        $sql = "";
    }

    public function updatePlan($data){
        $sql = "";
    }

    public function deletePlan($data){
        $sql = "";
    }

//    /**
//     * @return Plano[] Returns an array of Plano objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Plano
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
