<?php

namespace App\Repository;

use App\Entity\Box;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Box|null find($id, $lockMode = null, $lockVersion = null)
 * @method Box|null findOneBy(array $criteria, array $orderBy = null)
 * @method Box[]    findAll()
 * @method Box[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BoxRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Box::class);
    }

    /**
     * Encontra um box disponível (não ocupado)
     *
     * @return Box|null
     */
    public function findBoxDisponivel(): ?Box
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.ocupado = :ocupado')
            ->setParameter('ocupado', false)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Encontra todos os boxes ocupados
     *
     * @return Box[]
     */
    public function findBoxesOcupados(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.ocupado = :ocupado')
            ->setParameter('ocupado', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Encontra um box pelo número
     *
     * @param int $numero
     * @return Box|null
     */
    public function findByNumero(int $numero): ?Box
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.numero = :numero')
            ->setParameter('numero', $numero)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Conta o número total de boxes
     *
     * @return int
     */
    public function countBoxes(): int
    {
        return $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}