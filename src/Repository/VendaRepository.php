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
    public function findByData(int $baseId, \DateTime $data): array
    {
        $sql = "SELECT v.id, v.estabelecimento_id, v.pet_id, v.total, v.data,
                       v.status, v.metodo_pagamento, v.bandeira_cartao, v.parcelas,
                       v.origem, v.observacao, v.inativar,
                       p.nome  AS pet_nome,
                       c.id    AS cliente_id,
                       c.nome  AS cliente_nome,
                       c.telefone AS cliente_telefone
                FROM homepet_{$baseId}.venda v
                LEFT JOIN homepet_{$baseId}.pet    p ON p.id = v.pet_id
                LEFT JOIN homepet_{$baseId}.cliente c ON c.id = p.dono_id
                WHERE v.estabelecimento_id = :estab
                  AND DATE(v.data) = :data
                ORDER BY v.id DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'estab' => $baseId,
            'data'  => $data->format('Y-m-d'),
        ]);
    }

    /**
     * Retorna total por forma de pagamento no dia
     */
    public function totalPorFormaPagamento(int $baseId, \DateTime $data): array
    {
        $sql = "SELECT metodo_pagamento AS metodo,
                       COALESCE(SUM(total), 0) AS total
                FROM homepet_{$baseId}.venda
                WHERE estabelecimento_id = :baseId
                  AND DATE(data) = :data
                  AND status NOT IN ('Carrinho', 'Cancelada')
                GROUP BY metodo_pagamento
                ORDER BY metodo_pagamento ASC";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $baseId,
            'data'   => $data->format('Y-m-d'),
        ]);
    }

    /**
     * Total geral do dia (escalar — não hidrata entidades)
     */
    public function totalGeralDoDia(int $baseId, \DateTime $data): float
    {
        $sql = "SELECT COALESCE(SUM(total), 0) AS total
                FROM homepet_{$baseId}.venda
                WHERE estabelecimento_id = :estab
                  AND DATE(data) = :data
                  AND status NOT IN ('Carrinho', 'Cancelada')";

        return (float) $this->conn->fetchOne($sql, [
            'estab' => $baseId,
            'data'  => $data->format('Y-m-d'),
        ]);
    }

    /**
     * Vendas por período com LEFT JOIN em pet e cliente
     */
    public function findByPeriodo(int $baseId, \DateTime $inicio, \DateTime $fim): array
    {
        $sql = "SELECT v.id, v.pet_id, v.total, v.data, v.status,
                       v.metodo_pagamento, v.origem, v.observacao,
                       p.nome  AS pet_nome,
                       c.nome  AS cliente_nome
                FROM homepet_{$baseId}.venda v
                LEFT JOIN homepet_{$baseId}.pet    p ON p.id = v.pet_id
                LEFT JOIN homepet_{$baseId}.cliente c ON c.id = p.dono_id
                WHERE v.estabelecimento_id = :estab
                  AND DATE(v.data) BETWEEN :inicio AND :fim
                ORDER BY v.data DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'estab'  => $baseId,
            'inicio' => $inicio->format('Y-m-d'),
            'fim'    => $fim->format('Y-m-d'),
        ]);
    }

    /**
     * Total por período (escalar)
     */
    public function totalPorPeriodo(int $baseId, \DateTime $inicio, \DateTime $fim): float
    {
        $sql = "SELECT COALESCE(SUM(total), 0) AS total
                FROM homepet_{$baseId}.venda
                WHERE estabelecimento_id = :estab
                  AND DATE(data) BETWEEN :inicio AND :fim
                  AND status NOT IN ('Carrinho', 'Cancelada')";

        return (float) $this->conn->fetchOne($sql, [
            'estab'  => $baseId,
            'inicio' => $inicio->format('Y-m-d'),
            'fim'    => $fim->format('Y-m-d'),
        ]);
    }

    /**
     * Vendas em carrinho com dados de pet e cliente via LEFT JOIN
     */
    public function findCarrinho(int $baseId): array
    {
        $sql = "SELECT v.id, v.pet_id, v.total, v.data, v.origem, v.observacao,
                       p.nome AS pet_nome,
                       c.nome AS cliente_nome
                FROM homepet_{$baseId}.venda v
                LEFT JOIN homepet_{$baseId}.pet    p ON p.id = v.pet_id
                LEFT JOIN homepet_{$baseId}.cliente c ON c.id = p.dono_id
                WHERE v.estabelecimento_id = :baseId
                  AND v.status = 'Carrinho'
                ORDER BY v.data DESC";

        return $this->conn->fetchAllAssociative($sql, ['baseId' => $baseId]);
    }

    /**
     * Inativa (cancela) uma venda
     */
    /**
     * Requer o ENUM com 'Cancelada' — ver migrations/add_consulta_id_to_venda.sql.
     * Sem isso, em sql_mode STRICT o MySQL rejeita o UPDATE
     * ("Data truncated for column 'status'") sem que o erro chegue à tela.
     */
    public function inativar(int $baseId, int $vendaId): bool
    {
        $sql = "UPDATE homepet_{$baseId}.venda
                SET status = 'Cancelada'
                WHERE id = :id AND estabelecimento_id = :estab";

        return $this->conn->executeStatement($sql, [
            'id'    => $vendaId,
            'estab' => $baseId,
        ]) > 0;
    }

    /**
     * Vendas feitas na clínica (dentro das fichas dos pets) no período,
     * para o relatório de comissões.
     *
     * Cada venda é atribuída ao veterinário do atendimento do pet no mesmo
     * dia da venda (o mais recente do dia). Vendas sem atendimento
     * correspondente retornam veterinario_id NULL e são exibidas em um bloco
     * próprio no relatório. Vendas canceladas ficam de fora.
     */
    public function listarVendasClinicaComissao(int $baseId, \DateTime $inicio, \DateTime $fim): array
    {
        $sql = "SELECT v.id, v.data, v.total, v.pet_id, v.metodo_pagamento, v.status,
                       p.nome AS pet_nome,
                       COALESCE(cl.nome, v.cliente) AS cliente_nome,
                       (SELECT c.veterinario_id
                          FROM homepet_{$baseId}.consulta c
                         WHERE c.estabelecimento_id = :estab
                           AND c.pet_id = v.pet_id
                           AND c.data = DATE(v.data)
                           AND c.status <> 'cancelado'
                           AND c.veterinario_id IS NOT NULL
                         ORDER BY c.hora DESC, c.id DESC
                         LIMIT 1) AS veterinario_id
                FROM homepet_{$baseId}.venda v
                LEFT JOIN homepet_{$baseId}.pet p ON p.id = v.pet_id
                LEFT JOIN homepet_{$baseId}.cliente cl ON cl.id = p.dono_id
                WHERE v.estabelecimento_id = :estab
                  AND v.origem = 'clinica'
                  AND DATE(v.data) BETWEEN :inicio AND :fim
                  AND v.status <> 'Cancelada'
                ORDER BY v.data ASC, v.id ASC";

        return $this->conn->fetchAllAssociative($sql, [
            'estab'  => $baseId,
            'inicio' => $inicio->format('Y-m-d'),
            'fim'    => $fim->format('Y-m-d'),
        ]);
    }

    /**
     * Insere uma nova venda (origem clínica/PDV) e retorna o ID gerado.
     */
    public function inserirVenda(int $baseId, array $dados): int
    {
        $sql = "INSERT INTO homepet_{$baseId}.venda
                    (estabelecimento_id, cliente, pet_id, consulta_id, parcelas, origem,
                     metodo_pagamento, status, data, observacao, total)
                VALUES
                    (:estabelecimento_id, :cliente, :pet_id, :consulta_id, :parcelas, :origem,
                     :metodo_pagamento, :status, NOW(), :observacao, 0)";

        $this->conn->executeStatement($sql, [
            'estabelecimento_id' => $dados['estabelecimento_id'],
            'cliente'            => $dados['cliente'],
            'pet_id'             => $dados['pet_id'],
            'consulta_id'        => $dados['consulta_id'] ?? null,
            'parcelas'           => $dados['parcelas'],
            'origem'             => $dados['origem'],
            'metodo_pagamento'   => $dados['metodo_pagamento'],
            'status'             => $dados['status'],
            'observacao'         => $dados['observacao'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Atualiza o total de uma venda.
     */
    public function atualizarTotal(int $baseId, int $vendaId, float $total): void
    {
        $sql = "UPDATE homepet_{$baseId}.venda
                SET total = :total
                WHERE id = :id AND estabelecimento_id = :estab";

        $this->conn->executeStatement($sql, [
            'total' => $total,
            'id'    => $vendaId,
            'estab' => $baseId,
        ]);
    }

    /**
     * Busca uma venda pelo ID dentro do estabelecimento.
     */
    public function buscarPorId(int $baseId, int $vendaId): ?array
    {
        $sql = "SELECT *
                FROM homepet_{$baseId}.venda
                WHERE id = :id AND estabelecimento_id = :estab
                LIMIT 1";

        $row = $this->conn->fetchAssociative($sql, [
            'id'    => $vendaId,
            'estab' => $baseId,
        ]);

        return $row ?: null;
    }

    /**
     * Finaliza venda do carrinho — update atômico com parâmetros opcionais
     */
    /**
     * Busca uma venda em status Carrinho para finalização.
     * Retorna array com os dados ou null se não encontrada/já finalizada.
     */
    public function findVendaCarrinho(int $baseId, int $vendaId): ?array
    {
        $sql = "SELECT v.id, v.cliente, v.total, v.pet_id, v.origem, v.observacao, v.status
                FROM venda v
                WHERE v.id = :id
                  AND v.estabelecimento_id = :estab
                  AND v.status IN ('Aberta', 'Pendente')
                LIMIT 1";

        $row = $this->conn->fetchAssociative($sql, [
            'id'    => $vendaId,
            'estab' => $baseId,
        ]);

        return $row ?: null;
    }

    /**
     * Atualiza venda de Carrinho para o status final informado.
     * Retorna true se exatamente 1 linha foi afetada.
     */
    public function finalizarVenda(
        int $baseId,
        int $vendaId,
        string $metodoPagamento,
        string $statusFinal,
        ?string $bandeiraCartao = null,
        ?int $parcelas = null
    ): bool {
        $set    = "status = :status, metodo_pagamento = :metodo, data = NOW()";
        $params = [
            'status' => $statusFinal,
            'metodo' => $metodoPagamento,
            'id'     => $vendaId,
            'estab'  => $baseId,
        ];

        if ($bandeiraCartao !== null) {
            $set .= ", bandeira_cartao = :bandeira";
            $params['bandeira'] = $bandeiraCartao;
        }

        if ($parcelas !== null) {
            $set .= ", parcelas = :parcelas";
            $params['parcelas'] = $parcelas;
        }

        // ATENÇÃO: o WHERE anterior era
        //   ... AND estabelecimento_id = :estab AND status = 'Aberta' OR status = 'Pendente'
        // Como AND tem precedência sobre OR, a condição virava
        //   (id AND estab AND status='Aberta') OR (status='Pendente')
        // ou seja: qualquer venda Pendente da tabela inteira era atualizada.
        $sql = "UPDATE venda
                SET {$set}
                WHERE id = :id
                  AND estabelecimento_id = :estab
                  AND status IN ('Aberta', 'Pendente')";

        return $this->conn->executeStatement($sql, $params) > 0;
    }

    /**
     * Insere entrada no financeiro para venda PDV paga.
     */
    public function inserirFinanceiro(
        int $baseId,
        string $metodo,
        float $total,
        string $nomeCliente,
        ?int $petId,
        int $vendaId
    ): void {
        $sql = "INSERT INTO financeiro
                    (estabelecimento_id, descricao, valor, data, metodo_pagamento,
                     tipo, status, origem, pet_id)
                VALUES
                    (:estab, :descricao, :valor, NOW(), :metodo,
                     'ENTRADA', 'Pago', 'PDV', :pet_id)";

        $this->conn->executeStatement($sql, [
            'estab'     => $baseId,
            'descricao' => "Venda PDV #{$vendaId} — {$nomeCliente}",
            'valor'     => $total,
            'metodo'    => $metodo,
            'pet_id'    => $petId,
        ]);
    }

    /**
     * Busca vendas de um pet com LEFT JOIN em pet e cliente
     */
    public function findByPet(int $baseId, int $petId, $status = null): array
    {
        $params = ['estab' => $baseId, 'petId' => $petId];

        if ($status !== null) {
            $sqlPart          = 'v.status = :status';
            $params['status'] = (string) $status;
        } else {
            $sqlPart = "v.status NOT IN ('Aberta', 'Cancelada')";
        }

        $sql = "SELECT v.id, v.pet_id, v.consulta_id, v.total, v.data, v.status,
                       v.metodo_pagamento, v.origem, v.observacao,
                       p.nome  AS pet_nome,
                       c.nome  AS cliente_nome,
                       cs.data AS consulta_data,
                       cs.hora AS consulta_hora,
                       cs.tipo AS consulta_tipo,
                       vt.nome AS consulta_veterinario
                FROM homepet_{$baseId}.venda v
                LEFT JOIN homepet_{$baseId}.pet     p  ON p.id  = v.pet_id
                LEFT JOIN homepet_{$baseId}.cliente c  ON c.id  = p.dono_id
                LEFT JOIN homepet_{$baseId}.consulta cs ON cs.id = v.consulta_id
                LEFT JOIN homepet_{$baseId}.veterinario vt ON vt.id = cs.veterinario_id
                WHERE v.estabelecimento_id = :estab
                  AND v.pet_id = :petId
                  AND {$sqlPart}
                ORDER BY v.data DESC, v.id DESC";

        return $this->conn->fetchAllAssociative($sql, $params);
    }

    /**
     * Busca vendas de um pet por status.
     *
     * Antes: status era concatenado direto no SQL (injeção) e o Result era
     * usado como Statement (`$query->fetchAllAssociative($sql)`), o que só
     * funcionava por acaso. Agora usa bind + fetchAllAssociative da Connection.
     */
    public function vendaPorStatus(int $baseId, int $petId, $status): array
    {
        return $this->findByPet($baseId, $petId, $status);
    }

    /**
     * Vendas de um pet agrupadas por atendimento (consulta).
     *
     * Retorna uma lista ordenada de "grupos", cada um com os dados do
     * atendimento e as vendas correspondentes. Vendas sem consulta_id caem
     * no grupo especial de chave 0 ("Sem atendimento vinculado").
     *
     * @param string[] $statusIn Status considerados (padrão: tudo menos canceladas)
     */
    public function listarPorPetAgrupadoPorConsulta(
        int $baseId,
        int $petId,
        array $statusIn = ['Aberta', 'Pendente', 'Paga', 'Carrinho']
    ): array {
        $sql = "SELECT v.id, v.pet_id, v.consulta_id, v.total, v.data, v.status,
                       v.metodo_pagamento, v.origem, v.observacao,
                       cs.data AS consulta_data,
                       cs.hora AS consulta_hora,
                       cs.tipo AS consulta_tipo,
                       vt.nome AS consulta_veterinario
                FROM homepet_{$baseId}.venda v
                LEFT JOIN homepet_{$baseId}.consulta cs   ON cs.id = v.consulta_id
                LEFT JOIN homepet_{$baseId}.veterinario vt ON vt.id = cs.veterinario_id
                WHERE v.estabelecimento_id = :estab
                  AND v.pet_id = :petId
                  AND v.status IN (:statusIn)
                ORDER BY (v.consulta_id IS NULL) ASC,
                         cs.data DESC, cs.hora DESC,
                         v.data DESC, v.id DESC";

        $linhas = $this->conn->fetchAllAssociative(
            $sql,
            ['estab' => $baseId, 'petId' => $petId, 'statusIn' => $statusIn],
            ['statusIn' => \Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $grupos = [];

        foreach ($linhas as $linha) {
            $chave = (int) ($linha['consulta_id'] ?? 0);

            if (! isset($grupos[$chave])) {
                $grupos[$chave] = [
                    'consulta_id'  => $chave ?: null,
                    'data'         => $linha['consulta_data'] ?? null,
                    'hora'         => $linha['consulta_hora'] ?? null,
                    'tipo'         => $linha['consulta_tipo'] ?? null,
                    'veterinario'  => $linha['consulta_veterinario'] ?? null,
                    'vendas'       => [],
                    'total'        => 0.0,
                    'qtd_vendas'   => 0,
                ];
            }

            $grupos[$chave]['vendas'][] = $linha;
            $grupos[$chave]['total'] += (float) $linha['total'];
            $grupos[$chave]['qtd_vendas']++;
        }

        return $grupos;
    }

    /**
     * Resumo de vendas do dia — COUNT e SUM em query única
     */
    public function getResumoDoDia(int $baseId, \DateTime $data): array
    {
        $sql = "SELECT COUNT(*) AS quantidade,
                       COALESCE(SUM(total), 0) AS total
                FROM homepet_{$baseId}.venda
                WHERE estabelecimento_id = :baseId
                  AND DATE(data) = :data
                  AND status NOT IN ('Carrinho', 'Cancelada')";

        $result    = $this->conn->fetchAssociative($sql, [
            'baseId' => $baseId,
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