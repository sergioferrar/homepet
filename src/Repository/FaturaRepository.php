<?php

namespace App\Repository;

use App\Entity\Fatura;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FaturaRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fatura::class);
        $this->conn = $this->getEntityManager()->getConnection();
        error_log('InvoiceRepository constructed at ' . microtime(true));
    }

    public function add(Fatura $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }

    public function remove(Fatura $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) { $this->getEntityManager()->flush(); }
    }

    /**
     * Busca invoices de um estabelecimento com dados do plano via LEFT JOIN
     */
    public function findByEstabelecimento(int $estabelecimentoId): array
    {
        $sql = "SELECT i.id, i.estabelecimento_id, i.status, i.valor_total, i.valor_desconto,
                       i.data_emissao, i.data_vencimento, i.data_pagamento, i.referencia,
                       e.razaoSocial AS estabelecimento_nome, e.cnpj,
                       p.titulo AS plano_nome, p.valor AS plano_valor
                FROM invoice i
                LEFT JOIN estabelecimento e ON e.id = i.estabelecimento_id
                LEFT JOIN planos p ON p.id = e.planoId
                WHERE i.estabelecimento_id = :estabelecimentoId
                ORDER BY i.data_emissao DESC";

        return $this->conn->fetchAllAssociative($sql, ['estabelecimentoId' => $estabelecimentoId]);
    }

    /**
     * Invoices pendentes e vencidos com dados do estabelecimento
     */
    public function findPendingInvoices(): array
    {
        $sql = "SELECT i.id, i.estabelecimento_id, i.status, i.valor_total,
                       i.data_emissao, i.data_vencimento,
                       e.razaoSocial AS estabelecimento_nome, e.cnpj,
                       DATEDIFF(NOW(), i.data_vencimento) AS dias_atraso
                FROM invoice i
                LEFT JOIN estabelecimento e ON e.id = i.estabelecimento_id
                WHERE i.status = 'pendente'
                  AND i.data_vencimento < NOW()
                ORDER BY i.data_vencimento ASC";

        return $this->conn->fetchAllAssociative($sql);
    }

    /**
     * Receita total de um período (invoices pagos)
     */
    public function getTotalReceitaMes(\DateTime $inicio, \DateTime $fim): float
    {
        $sql = "SELECT COALESCE(SUM(i.valor_total - COALESCE(i.valor_desconto, 0)), 0) AS total
                FROM invoice i
                WHERE i.status = 'pago'
                  AND i.data_pagamento BETWEEN :inicio AND :fim";

        return (float) $this->conn->fetchOne($sql, [
            'inicio' => $inicio->format('Y-m-d 00:00:00'),
            'fim'    => $fim->format('Y-m-d 23:59:59'),
        ]);
    }

    /**
     * Quantidade e valor agrupados por status
     */
    public function getInvoicesPorStatus(): array
    {
        $sql = "SELECT i.status,
                       COUNT(i.id) AS quantidade,
                       COALESCE(SUM(i.valor_total - COALESCE(i.valor_desconto, 0)), 0) AS total
                FROM invoice i
                GROUP BY i.status
                ORDER BY i.status";

        return $this->conn->fetchAllAssociative($sql);
    }

    /**
     * Últimos N invoices com nome do estabelecimento
     */
    public function findUltimos(int $limite = 10): array
    {
        $sql = "SELECT i.id, i.status, i.valor_total, i.data_emissao, i.data_vencimento, i.data_pagamento,
                       e.razaoSocial AS estabelecimento_nome
                FROM invoice i
                LEFT JOIN estabelecimento e ON e.id = i.estabelecimento_id
                ORDER BY i.data_emissao DESC
                LIMIT :limite";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('limite', $limite, \PDO::PARAM_INT);
        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * Receita agrupada por mês para relatório anual
     */
    public function getReceitaPorMesAno(int $ano): array
    {
        $sql = "SELECT MONTH(i.data_pagamento) AS mes,
                       COUNT(i.id) AS quantidade,
                       COALESCE(SUM(i.valor_total - COALESCE(i.valor_desconto, 0)), 0) AS total
                FROM invoice i
                WHERE i.status = 'pago'
                  AND YEAR(i.data_pagamento) = :ano
                GROUP BY MONTH(i.data_pagamento)
                ORDER BY mes ASC";

        return $this->conn->fetchAllAssociative($sql, ['ano' => $ano]);
    }

    /**
     * Marcar invoice como pago
     */
    public function marcarPago(int $id): void
    {
        $sql = "UPDATE invoice
                SET status = 'pago', data_pagamento = NOW()
                WHERE id = :id";

        $this->conn->executeQuery($sql, ['id' => $id]);
    }
}