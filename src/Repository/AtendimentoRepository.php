<?php

namespace App\Repository;

use App\Entity\Clinica\Atendimento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Atendimento|null find($id, $lockMode = null, $lockVersion = null)
 * @method Atendimento|null findOneBy(array $criteria, array $orderBy = null)
 * @method Atendimento[]    findAll()
 * @method Atendimento[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AtendimentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Atendimento::class);
    }

    /**
     * Encontra atendimentos agendados para hoje
     *
     * @return Atendimento[]
     */
    public function findAtendimentosHoje(): array
    {
        $hoje = new \DateTime();
        $hoje->setTime(0, 0, 0);
        $amanha = clone $hoje;
        $amanha->modify('+1 day');

        return $this->createQueryBuilder('a')
            ->andWhere('a.dataHora >= :hoje')
            ->andWhere('a.dataHora < :amanha')
            ->setParameter('hoje', $hoje)
            ->setParameter('amanha', $amanha)
            ->orderBy('a.dataHora', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encontra atendimentos com base em filtros
     *
     * @param string $filtro
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return Atendimento[]
     */
    public function findByFiltro(string $filtro, ?string $dataInicio, ?string $dataFim): array
    {
        $qb = $this->createQueryBuilder('a');

        if ($filtro !== 'todos') {
            $status = str_replace('_', ' ', $filtro);
            $status = ucfirst($status);
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $status);
        }

        if ($dataInicio) {
            $inicio = new \DateTime($dataInicio);
            $inicio->setTime(0, 0, 0);
            $qb->andWhere('a.dataHora >= :inicio')
               ->setParameter('inicio', $inicio);
        }

        if ($dataFim) {
            $fim = new \DateTime($dataFim);
            $fim->setTime(23, 59, 59);
            $qb->andWhere('a.dataHora <= :fim')
               ->setParameter('fim', $fim);
        }

        return $qb->orderBy('a.dataHora', 'DESC')
                 ->getQuery()
                 ->getResult();
    }

    /**
     * Gera relatório de atendimentos por período
     *
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return array
     */
    public function gerarRelatorioAtendimentos(?string $dataInicio, ?string $dataFim): array
    {
        $qb = $this->createQueryBuilder('a')
                   ->select('COUNT(a.id) as total', 'a.status')
                   ->groupBy('a.status');

        if ($dataInicio) {
            $inicio = new \DateTime($dataInicio);
            $inicio->setTime(0, 0, 0);
            $qb->andWhere('a.dataHora >= :inicio')
               ->setParameter('inicio', $inicio);
        }

        if ($dataFim) {
            $fim = new \DateTime($dataFim);
            $fim->setTime(23, 59, 59);
            $qb->andWhere('a.dataHora <= :fim')
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
     * Gera relatório financeiro por período
     *
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return array
     */
    public function gerarRelatorioFinanceiro(?string $dataInicio, ?string $dataFim): array
    {
        $qb = $this->createQueryBuilder('a')
                   ->select('SUM(a.valorTotal) as total', 'COUNT(a.id) as quantidade', 'a.status');

        if ($dataInicio) {
            $inicio = new \DateTime($dataInicio);
            $inicio->setTime(0, 0, 0);
            $qb->andWhere('a.dataHora >= :inicio')
               ->setParameter('inicio', $inicio);
        }

        if ($dataFim) {
            $fim = new \DateTime($dataFim);
            $fim->setTime(23, 59, 59);
            $qb->andWhere('a.dataHora <= :fim')
               ->setParameter('fim', $fim);
        }

        $result = $qb->groupBy('a.status')
                     ->getQuery()
                     ->getResult();
        
        // Formatar resultado
        $relatorio = [
            'total_valor' => 0,
            'total_quantidade' => 0,
            'por_status' => [],
            'dados' => $result
        ];
        
        foreach ($result as $item) {
            if ($item['status'] === 'Finalizado') {
                $relatorio['total_valor'] += $item['total'];
            }
            $relatorio['total_quantidade'] += $item['quantidade'];
            $relatorio['por_status'][$item['status']] = [
                'valor' => $item['total'],
                'quantidade' => $item['quantidade']
            ];
        }
        
        return $relatorio;
    }

    /**
     * Salva uma entidade Atendimento
     *
     * @param Atendimento $entity
     * @param bool $flush
     */
    public function save(Atendimento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove uma entidade Atendimento
     *
     * @param Atendimento $entity
     * @param bool $flush
     */
    public function remove(Atendimento $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
