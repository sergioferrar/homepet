<?php

namespace App\Repository;

use App\Entity\Internacao;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Internacao|null find($id, $lockMode = null, $lockVersion = null)
 * @method Internacao|null findOneBy(array $criteria, array $orderBy = null)
 * @method Internacao[]    findAll()
 * @method Internacao[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InternacaoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Internacao::class);
    }

    /**
     * Encontra internações ativas
     *
     * @return Internacao[]
     */
    public function findInternacoesAtivas(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->setParameter('status', 'ativa')
            ->orderBy('i.dataInicio', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encontra internações com base em filtros
     *
     * @param string $filtro
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return Internacao[]
     */
    public function findByFiltro(string $filtro, ?string $dataInicio, ?string $dataFim): array
    {
        $qb = $this->createQueryBuilder('i');

        if ($filtro !== 'todos') {
            $qb->andWhere('i.status = :status')
               ->setParameter('status', $filtro);
        }

        if ($dataInicio) {
            $inicio = new \DateTime($dataInicio);
            $inicio->setTime(0, 0, 0);
            $qb->andWhere('i.dataInicio >= :inicio')
               ->setParameter('inicio', $inicio);
        }

        if ($dataFim) {
            $fim = new \DateTime($dataFim);
            $fim->setTime(23, 59, 59);
            $qb->andWhere('i.dataInicio <= :fim')
               ->setParameter('fim', $fim);
        }

        return $qb->orderBy('i.dataInicio', 'DESC')
                 ->getQuery()
                 ->getResult();
    }

    /**
     * Gera relatório de internações por período
     *
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return array
     */
    public function gerarRelatorioInternacoes(?string $dataInicio, ?string $dataFim): array
    {
        $qb = $this->createQueryBuilder('i')
                   ->select('COUNT(i.id) as total', 'i.status')
                   ->groupBy('i.status');

        if ($dataInicio) {
            $inicio = new \DateTime($dataInicio);
            $inicio->setTime(0, 0, 0);
            $qb->andWhere('i.dataInicio >= :inicio')
               ->setParameter('inicio', $inicio);
        }

        if ($dataFim) {
            $fim = new \DateTime($dataFim);
            $fim->setTime(23, 59, 59);
            $qb->andWhere('i.dataInicio <= :fim')
               ->setParameter('fim', $fim);
        }

        $result = $qb->getQuery()->getResult();
        
        // Formatar resultado
        $relatorio = [
            'total' => 0,
            'por_status' => [],
            'dados' => $result
        ];
        
        foreach ($result as $item) {
            $relatorio['por_status'][$item['status']] = $item['total'];
            $relatorio['total'] += $item['total'];
        }
        
        return $relatorio;
    }

    /**
     * Salva uma entidade Internacao
     *
     * @param Internacao $entity
     * @param bool $flush
     */
    public function save(Internacao $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove uma entidade Internacao
     *
     * @param Internacao $entity
     * @param bool $flush
     */
    public function remove(Internacao $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
