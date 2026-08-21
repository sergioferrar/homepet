<?php

namespace App\Repository;

use App\Entity\PesoPet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PesoPet>
 */
class PesoPetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PesoPet::class);
    }

    /**
     * Busca o histórico de peso de um pet (limitado para performance)
     */
    public function buscarHistoricoPeso(int $estabelecimentoId, int $petId, int $limite = 20): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.estabelecimentoId = :estabelecimentoId')
            ->andWhere('p.petId = :petId')
            ->setParameter('estabelecimentoId', $estabelecimentoId)
            ->setParameter('petId', $petId)
            ->orderBy('p.data', 'DESC')
            ->addOrderBy('p.hora', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca o último peso registrado do pet
     */
    public function buscarUltimoPeso(int $estabelecimentoId, int $petId): ?PesoPet
    {
        return $this->createQueryBuilder('p')
            ->where('p.estabelecimentoId = :estabelecimentoId')
            ->andWhere('p.petId = :petId')
            ->setParameter('estabelecimentoId', $estabelecimentoId)
            ->setParameter('petId', $petId)
            ->orderBy('p.data', 'DESC')
            ->addOrderBy('p.hora', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Salva um novo registro de peso
     */
    public function salvarPeso(PesoPet $pesoPet): void
    {
        $this->getEntityManager()->persist($pesoPet);
        $this->getEntityManager()->flush();
    }

    /**
     * Remove um registro de peso
     */
    public function removerPeso(PesoPet $pesoPet): void
    {
        $this->getEntityManager()->remove($pesoPet);
        $this->getEntityManager()->flush();
    }
}