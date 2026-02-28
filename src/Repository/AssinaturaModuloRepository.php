<?php

namespace App\Repository;

use App\Entity\AssinaturaModulo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssinaturaModulo>
 */
class AssinaturaModuloRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssinaturaModulo::class);
    }

    /**
     * Retorna todos os módulos adicionais de um estabelecimento.
     *
     * @return AssinaturaModulo[]
     */
    public function findByEstabelecimento(int $estabelecimentoId): array
    {
        return $this->findBy(
            ['estabelecimentoId' => $estabelecimentoId],
            ['contratadoEm' => 'DESC']
        );
    }

    /**
     * Retorna apenas os módulos ATIVOS de um estabelecimento.
     *
     * @return AssinaturaModulo[]
     */
    public function findAtivos(int $estabelecimentoId): array
    {
        return $this->findBy([
            'estabelecimentoId' => $estabelecimentoId,
            'status'            => AssinaturaModulo::STATUS_ATIVO,
        ]);
    }

    /**
     * Verifica se o estabelecimento já tem um módulo específico ativo ou pendente.
     */
    public function jaContratado(int $estabelecimentoId, int $moduloId): bool
    {
        $result = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.estabelecimentoId = :eid')
            ->andWhere('a.moduloId = :mid')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('eid', $estabelecimentoId)
            ->setParameter('mid', $moduloId)
            ->setParameter('statuses', [
                AssinaturaModulo::STATUS_ATIVO,
                AssinaturaModulo::STATUS_PENDENTE,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }

    /**
     * Soma o total mensal dos módulos adicionais ativos de um estabelecimento.
     */
    public function totalMensalAtivos(int $estabelecimentoId): float
    {
        $result = $this->createQueryBuilder('a')
            ->select('SUM(a.valorMensal)')
            ->where('a.estabelecimentoId = :eid')
            ->andWhere('a.status = :status')
            ->setParameter('eid', $estabelecimentoId)
            ->setParameter('status', AssinaturaModulo::STATUS_ATIVO)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Retorna um módulo contratado por estabelecimento + módulo (qualquer status).
     */
    public function findOneByEstabelecimentoModulo(int $eid, int $mid): ?AssinaturaModulo
    {
        return $this->findOneBy([
            'estabelecimentoId' => $eid,
            'moduloId'          => $mid,
        ]);
    }

    public function add(AssinaturaModulo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AssinaturaModulo $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
