<?php

namespace App\Repository;

use App\Entity\Estabelecimento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Estabelecimento>
 *
 * @method Estabelecimento|null find($id, $lockMode = null, $lockVersion = null)
 * @method Estabelecimento|null findOneBy(array $criteria, array $orderBy = null)
 * @method Estabelecimento[]    findAll()
 * @method Estabelecimento[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EstabelecimentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Estabelecimento::class);
    }

    public function add(Estabelecimento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Estabelecimento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function verificaDatabase($baseId)
    {
        $sql = "SELECT SCHEMA_NAME
            FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME ='homepet_{$baseId}'";

        $query = $this->conn->query($sql);
        return $query->fetch();
    }

//    /**
//     * @return Estabelecimento[] Returns an array of Estabelecimento objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Estabelecimento
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
