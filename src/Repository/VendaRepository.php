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

    /**
     * 🔹 Inativa uma venda (muda o status para 'Inativa')
     */
    public function inativar(int $estabelecimentoId, int $vendaId): void
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            UPDATE venda
            SET status = 'Inativa'
            WHERE estabelecimento_id = :baseId
              AND id = :vendaId
        ";

        $conn->executeStatement($sql, [
            'baseId' => $estabelecimentoId,
            'vendaId' => $vendaId,
        ]);
    }

    /**
     * 🔹 Busca vendas em carrinho (aguardando finalização no PDV)
     */
    public function findCarrinho(int $estabelecimentoId): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT v.id, v.pet_id, v.total, v.data, v.origem, v.observacao,
                   p.nome AS pet_nome, c.nome AS cliente_nome
            FROM venda v
            LEFT JOIN pet p ON v.pet_id = p.id
            LEFT JOIN cliente c ON p.dono_id = c.id
            WHERE v.estabelecimento_id = :baseId
              AND v.status = 'Carrinho'
            ORDER BY v.data DESC
        ";

        return $conn->fetchAllAssociative($sql, [
            'baseId' => $estabelecimentoId,
        ]);
    }

    /**
     * 🔹 Finaliza uma venda do carrinho (muda status para Aberta ou Pendente)
     */
    public function finalizarVenda(int $estabelecimentoId, int $vendaId, string $metodoPagamento, ?string $bandeiraCartao = null, ?int $parcelas = null): void
    {
        $conn = $this->getEntityManager()->getConnection();

        $status = ($metodoPagamento === 'pendente') ? 'Pendente' : 'Aberta';

        $sql = "
            UPDATE venda
            SET status = :status,
                metodo_pagamento = :metodoPagamento,
                bandeira_cartao = :bandeiraCartao,
                parcelas = :parcelas,
                data = NOW()
            WHERE estabelecimento_id = :baseId
              AND id = :vendaId
        ";

        $conn->executeStatement($sql, [
            'status' => $status,
            'metodoPagamento' => $metodoPagamento,
            'bandeiraCartao' => $bandeiraCartao,
            'parcelas' => $parcelas,
            'baseId' => $estabelecimentoId,
            'vendaId' => $vendaId,
        ]);
    }
}
