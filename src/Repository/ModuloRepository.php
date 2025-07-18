<?php

namespace App\Repository;

use App\Entity\Modulo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Modulo>
 *
 * @method Modulo|null find($id, $lockMode = null, $lockVersion = null)
 * @method Modulo|null findOneBy(array $criteria, array $orderBy = null)
 * @method Modulo[]    findAll()
 * @method Modulo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ModuloRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Modulo::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function getIdPlanoByDescricao($descricao)
    {
        $sql = "SELECT id, titulo";

        $query = $this->conn->executeQuery($sql);
        return $query->fetchAssociative();
    }

    public function add(Modulo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Modulo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return Modulo[] Returns an array of Modulo objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('m.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Modulo
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
