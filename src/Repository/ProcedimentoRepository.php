<?php

namespace App\Repository;

use App\Entity\Procedimento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Procedimento|null find($id, $lockMode = null, $lockVersion = null)
 * @method Procedimento|null findOneBy(array $criteria, array $orderBy = null)
 * @method Procedimento[]    findAll()
 * @method Procedimento[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProcedimentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Procedimento::class);
    }

    /**
     * Encontra procedimentos ativos
     *
     * @return Procedimento[]
     */
    public function findAtivos(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.ativo = :ativo')
            ->setParameter('ativo', true)
            ->orderBy('p.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encontra procedimentos por termo de busca
     *
     * @param string $termo
     * @return Procedimento[]
     */
    public function findByTermo(string $termo): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.nome LIKE :termo OR p.descricao LIKE :termo')
            ->setParameter('termo', '%' . $termo . '%')
            ->orderBy('p.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Salva uma entidade Procedimento
     *
     * @param Procedimento $entity
     * @param bool $flush
     */
    public function save(Procedimento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove uma entidade Procedimento
     *
     * @param Procedimento $entity
     * @param bool $flush
     */
    public function remove(Procedimento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
