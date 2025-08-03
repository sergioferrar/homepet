<?php

namespace App\Repository;

use App\Entity\Veterinario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Veterinario|null find($id, $lockMode = null, $lockVersion = null)
 * @method Veterinario|null findOneBy(array $criteria, array $orderBy = null)
 * @method Veterinario[]    findAll()
 * @method Veterinario[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VeterinarioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Veterinario::class);
    }

    /**
     * Encontra veterinários por estabelecimento.
     *
     * @param int $estabelecimentoId
     * @return Veterinario[]
     */
    public function findByEstabelecimento(int $estabelecimentoId): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.estabelecimentoId = :estabelecimentoId')
            ->setParameter('estabelecimentoId', $estabelecimentoId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Encontra veterinários por especialidade
     *
     * @param string $especialidade
     * @return Veterinario[]
     */
    public function findByEspecialidade(string $especialidade): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.especialidade = :especialidade')
            ->setParameter('especialidade', $especialidade)
            ->getQuery()
            ->getResult();
    }

    /**
     * Encontra veterinários por nome parcial (case-insensitive)
     *
     * @param string $nome
     * @return Veterinario[]
     */
    public function findByNomeLike(string $nome): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('LOWER(v.nome) LIKE :nome')
            ->setParameter('nome', '%' . strtolower($nome) . '%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Conta o número total de veterinários
     *
     * @return int
     */
    public function countVeterinarios(): int
    {
        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}