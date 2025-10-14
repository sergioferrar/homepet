<?php

namespace App\Repository;

use App\Entity\Venda;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Venda>
 *
 * @method Venda|null find($id, $lockMode = null, $lockVersion = null)
 * @method Venda|null findOneBy(array $criteria, array $orderBy = null)
 * @method Venda[]    findAll()
 * @method Venda[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VendaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Venda::class);
    }

    /**
     * 🔹 Retorna todas as vendas de um estabelecimento por data (formato YYYY-MM-DD)
     */
    public function findByData(int $estabelecimentoId, \DateTime $data): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT *
            FROM venda
            WHERE estabelecimento_id = :baseId
              AND DATE(data) = :hoje
            ORDER BY id DESC
        ";

        return $conn->fetchAllAssociative($sql, [
            'baseId' => $estabelecimentoId,
            'hoje'   => $data->format('Y-m-d'),
        ]);
    }

    /**
     * 🔹 Retorna o total de vendas agrupado por forma de pagamento no dia informado
     */
    public function totalPorFormaPagamento(int $estabelecimentoId, \DateTime $data): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT 
                metodo_pagamento AS metodo,
                COALESCE(SUM(total), 0) AS total
            FROM venda
            WHERE estabelecimento_id = :baseId
              AND DATE(data) = :hoje
            GROUP BY metodo_pagamento
            ORDER BY metodo_pagamento ASC
        ";

        return $conn->fetchAllAssociative($sql, [
            'baseId' => $estabelecimentoId,
            'hoje'   => $data->format('Y-m-d'),
        ]);
    }

    /**
     * 🔹 Retorna o total geral das vendas do dia (soma de todas)
     */
    public function totalGeralDoDia(int $estabelecimentoId, \DateTime $data): float
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT 
                COALESCE(SUM(total), 0) AS total
            FROM venda
            WHERE estabelecimento_id = :baseId
              AND DATE(data) = :hoje
        ";

        $result = $conn->fetchAssociative($sql, [
            'baseId' => $estabelecimentoId,
            'hoje'   => $data->format('Y-m-d'),
        ]);

        return $result && isset($result['total']) ? (float)$result['total'] : 0.0;
    }

    /**
     * 🔹 Retorna todas as vendas entre duas datas (para relatórios)
     */
    public function findByPeriodo(int $estabelecimentoId, \DateTime $inicio, \DateTime $fim): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT *
            FROM venda
            WHERE estabelecimento_id = :baseId
              AND DATE(data) BETWEEN :inicio AND :fim
            ORDER BY data DESC
        ";

        return $conn->fetchAllAssociative($sql, [
            'baseId' => $estabelecimentoId,
            'inicio' => $inicio->format('Y-m-d'),
            'fim'    => $fim->format('Y-m-d'),
        ]);
    }

    /**
     * 🔹 Retorna o total consolidado de vendas no período informado
     */
    public function totalPorPeriodo(int $estabelecimentoId, \DateTime $inicio, \DateTime $fim): float
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT COALESCE(SUM(total), 0) AS total
            FROM venda
            WHERE estabelecimento_id = :baseId
              AND DATE(data) BETWEEN :inicio AND :fim
        ";

        $result = $conn->fetchAssociative($sql, [
            'baseId' => $estabelecimentoId,
            'inicio' => $inicio->format('Y-m-d'),
            'fim'    => $fim->format('Y-m-d'),
        ]);

        return $result && isset($result['total']) ? (float)$result['total'] : 0.0;
    }
}
