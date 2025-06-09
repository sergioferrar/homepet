<?php

namespace App\Repository;

use App\Entity\MenuGrupoModulo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuGrupoModulo>
 *
 * @method MenuGrupoModulo|null find($id, $lockMode = null, $lockVersion = null)
 * @method MenuGrupoModulo|null findOneBy(array $criteria, array $orderBy = null)
 * @method MenuGrupoModulo[]    findAll()
 * @method MenuGrupoModulo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MenuGrupoModuloRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuGrupoModulo::class);
    }

    public function add(MenuGrupoModulo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MenuGrupoModulo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return MenuGrupoModulo[] Returns an array of MenuGrupoModulo objects
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

//    public function findOneBySomeField($value): ?MenuGrupoModulo
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
