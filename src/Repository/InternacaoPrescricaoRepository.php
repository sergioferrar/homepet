<?php

namespace App\Repository;

use App\Entity\InternacaoPrescricao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InternacaoPrescricao>
 *
 * @method InternacaoPrescricao|null find($id, $lockMode = null, $lockVersion = null)
 * @method InternacaoPrescricao|null findOneBy(array $criteria, array $orderBy = null)
 * @method InternacaoPrescricao[]    findAll()
 * @method InternacaoPrescricao[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InternacaoPrescricaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InternacaoPrescricao::class);
    }

    /**
     * Busca prescrições apenas da internação específica.
     * IMPORTANTE: Garante que apenas prescrições desta internação são retornadas.
     *
     * @param int $internacaoId ID da internação
     * @return InternacaoPrescricao[] Array de prescrições
     */
    public function findByInternacao(int $internacaoId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.internacaoId = :internacaoId')
            ->setParameter('internacaoId', $internacaoId)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca uma prescrição específica garantindo que ela pertence à internação.
     *
     * @param int $prescricaoId ID da prescrição
     * @param int $internacaoId ID da internação (verificação de segurança)
     * @return InternacaoPrescricao|null
     */
    public function findByIdAndInternacao(int $prescricaoId, int $internacaoId): ?InternacaoPrescricao
    {
        return $this->createQueryBuilder('p')
            ->where('p.id = :prescricaoId')
            ->andWhere('p.internacaoId = :internacaoId')
            ->setParameter('prescricaoId', $prescricaoId)
            ->setParameter('internacaoId', $internacaoId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
