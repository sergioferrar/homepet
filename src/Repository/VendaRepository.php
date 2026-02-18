<?php

namespace App\Repository;

use App\Entity\Venda;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repositório de Vendas com isolamento por tenant — SQL nativo com LEFT JOINs.
 *
 * @extends ServiceEntityRepository<Venda>
 * @method Venda|null find($id, $lockMode = null, $lockVersion = null)
 * @method Venda|null findOneBy(array $criteria, array $orderBy = null)
 * @method Venda[]    findAll()
 * @method Venda[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VendaRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Venda::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    /**
     * Busca vendas por data com LEFT JOIN em pet e cliente — evita N+1 queries.
     */
    public function findByData(int $estabelecimentoId, \DateTime $data): array
    {
        $sql = "SELECT v.id, v.estabelecimento_id, v.pet_id, v.total, v.data,
                       v.status, v.metodo_pagamento, v.bandeira_cartao, v.parcelas,
                       v.origem, v.observacao, v.inativar,
                       p.nome  AS pet_nome,
                       c.id    AS cliente_id,
                       c.nome  AS cliente_nome,
                       c.telefone AS cliente_telefone
                FROM {$_ENV['DBNAMETENANT']}.venda v
                LEFT JOIN {$_ENV['DBNAMETENANT']}.pet    p ON p.id = v.pet_id
                LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON c.id = p.dono_id
                WHERE v.estabelecimento_id = :estab
                  AND DATE(v.data) = :data
                ORDER BY v.id DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'estab' => $estabelecimentoId,
            'data'  => $data->format('Y-m-d'),
        ]);
    }

    /**
     * Retorna total por forma de pagamento no dia
     */
    public function totalPorFormaPagamento(int $estabelecimentoId, \DateTime $data): array
    {
        $sql = "SELECT metodo_pagamento AS metodo,
                       COALESCE(SUM(total), 0) AS total
                FROM {$_ENV['DBNAMETENANT']}.venda
                WHERE estabelecimento_id = :baseId
                  AND DATE(data) = :data
                  AND status NOT IN ('Carrinho', 'Cancelada')
                GROUP BY metodo_pagamento
                ORDER BY metodo_pagamento ASC";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $estabelecimentoId,
            'data'   => $data->format('Y-m-d'),
        ]);
    }

    /**
     * Total geral do dia (escalar — não hidrata entidades)
     */
    public function totalGeralDoDia(int $estabelecimentoId, \DateTime $data): float
    {
        $sql = "SELECT COALESCE(SUM(total), 0) AS total
                FROM {$_ENV['DBNAMETENANT']}.venda
                WHERE estabelecimento_id = :estab
                  AND DATE(data) = :data
                  AND status NOT IN ('Carrinho', 'Cancelada')";

        return (float) $this->conn->fetchOne($sql, [
            'estab' => $estabelecimentoId,
            'data'  => $data->format('Y-m-d'),
        ]);
    }

    /**
     * Vendas por período com LEFT JOIN em pet e cliente
     */
    public function findByPeriodo(int $estabelecimentoId, \DateTime $inicio, \DateTime $fim): array
    {
        $sql = "SELECT v.id, v.pet_id, v.total, v.data, v.status,
                       v.metodo_pagamento, v.origem, v.observacao,
                       p.nome  AS pet_nome,
                       c.nome  AS cliente_nome
                FROM {$_ENV['DBNAMETENANT']}.venda v
                LEFT JOIN {$_ENV['DBNAMETENANT']}.pet    p ON p.id = v.pet_id
                LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON c.id = p.dono_id
                WHERE v.estabelecimento_id = :estab
                  AND DATE(v.data) BETWEEN :inicio AND :fim
                ORDER BY v.data DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'estab'  => $estabelecimentoId,
            'inicio' => $inicio->format('Y-m-d'),
            'fim'    => $fim->format('Y-m-d'),
        ]);
    }

    /**
     * Total por período (escalar)
     */
    public function totalPorPeriodo(int $estabelecimentoId, \DateTime $inicio, \DateTime $fim): float
    {
        $sql = "SELECT COALESCE(SUM(total), 0) AS total
                FROM {$_ENV['DBNAMETENANT']}.venda
                WHERE estabelecimento_id = :estab
                  AND DATE(data) BETWEEN :inicio AND :fim
                  AND status NOT IN ('Carrinho', 'Cancelada')";

        return (float) $this->conn->fetchOne($sql, [
            'estab'  => $estabelecimentoId,
            'inicio' => $inicio->format('Y-m-d'),
            'fim'    => $fim->format('Y-m-d'),
        ]);
    }

    /**
     * Vendas em carrinho com dados de pet e cliente via LEFT JOIN
     */
    public function findCarrinho(int $estabelecimentoId): array
    {
        $sql = "SELECT v.id, v.pet_id, v.total, v.data, v.origem, v.observacao,
                       p.nome AS pet_nome,
                       c.nome AS cliente_nome
                FROM {$_ENV['DBNAMETENANT']}.venda v
                LEFT JOIN {$_ENV['DBNAMETENANT']}.pet    p ON p.id = v.pet_id
                LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON c.id = p.dono_id
                WHERE v.estabelecimento_id = :baseId
                  AND v.status = 'Carrinho'
                ORDER BY v.data DESC";

        return $this->conn->fetchAllAssociative($sql, ['baseId' => $estabelecimentoId]);
    }

    /**
     * Inativa (cancela) uma venda
     */
    public function inativar(int $estabelecimentoId, int $vendaId): bool
    {
        $sql = "UPDATE {$_ENV['DBNAMETENANT']}.venda
                SET status = 'Cancelada'
                WHERE id = :id AND estabelecimento_id = :estab";

        return $this->conn->executeStatement($sql, [
            'id'    => $vendaId,
            'estab' => $estabelecimentoId,
        ]) > 0;
    }

    /**
     * Finaliza venda do carrinho — update atômico com parâmetros opcionais
     */
    public function finalizarVenda(
        int $estabelecimentoId,
        int $vendaId,
        string $metodoPagamento,
        ?string $bandeiraCartao = null,
        ?int $parcelas = null
    ): bool {
        $status = ($metodoPagamento === 'pendente') ? 'Pendente' : 'Aberta';

        $set    = "status = :status, metodo_pagamento = :metodo, data = NOW()";
        $params = [
            'status' => $status,
            'metodo' => $metodoPagamento,
            'id'     => $vendaId,
            'estab'  => $estabelecimentoId,
        ];

        if ($bandeiraCartao !== null) {
            $set .= ", bandeira_cartao = :bandeira";
            $params['bandeira'] = $bandeiraCartao;
        }

        if ($parcelas !== null) {
            $set .= ", parcelas = :parcelas";
            $params['parcelas'] = $parcelas;
        }

        $sql = "UPDATE {$_ENV['DBNAMETENANT']}.venda
                SET {$set}
                WHERE id = :id
                  AND estabelecimento_id = :estab
                  AND status = 'Carrinho'";

        return $this->conn->executeStatement($sql, $params) > 0;
    }

    /**
     * Busca vendas de um pet com LEFT JOIN em pet e cliente
     */
    public function findByPet(int $estabelecimentoId, int $petId): array
    {
        $sql = "SELECT v.id, v.pet_id, v.total, v.data, v.status,
                       v.metodo_pagamento, v.origem, v.observacao,
                       p.nome  AS pet_nome,
                       c.nome  AS cliente_nome
                FROM {$_ENV['DBNAMETENANT']}.venda v
                LEFT JOIN {$_ENV['DBNAMETENANT']}.pet    p ON p.id = v.pet_id
                LEFT JOIN {$_ENV['DBNAMETENANT']}.cliente c ON c.id = p.dono_id
                WHERE v.estabelecimento_id = :estab
                  AND v.pet_id = :petId
                  AND v.status NOT IN ('Carrinho', 'Cancelada')
                ORDER BY v.data DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'estab' => $estabelecimentoId,
            'petId' => $petId,
        ]);
    }

    /**
     * Resumo de vendas do dia — COUNT e SUM em query única
     */
    public function getResumoDoDia(int $estabelecimentoId, \DateTime $data): array
    {
        $sql = "SELECT COUNT(*) AS quantidade,
                       COALESCE(SUM(total), 0) AS total
                FROM {$_ENV['DBNAMETENANT']}.venda
                WHERE estabelecimento_id = :baseId
                  AND DATE(data) = :data
                  AND status NOT IN ('Carrinho', 'Cancelada')";

        $result    = $this->conn->fetchAssociative($sql, [
            'baseId' => $estabelecimentoId,
            'data'   => $data->format('Y-m-d'),
        ]);

        $quantidade = (int) ($result['quantidade'] ?? 0);
        $total      = (float) ($result['total'] ?? 0);

        return [
            'quantidade_vendas' => $quantidade,
            'total_vendas'      => $total,
            'ticket_medio'      => $quantidade > 0 ? $total / $quantidade : 0,
        ];
    }
}