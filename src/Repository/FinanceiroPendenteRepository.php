<?php
namespace App\Repository;

use App\Entity\FinanceiroPendente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FinanceiroPendenteRepository extends ServiceEntityRepository
{
    private $conn;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinanceiroPendente::class);
        $this->conn = $this->getEntityManager()->getConnection();
    }

    public function findByDate($baseId, \DateTime $data): array
    {
        $sql = "SELECT f.id, 
                       CONCAT('Serviço para ', p.nome, ' - Dono: ', c.nome) AS descricao, 
                       f.valor, f.data, f.pet_id, p.nome as pet_nome, c.nome as dono_nome, f.metodo_pagamento
                FROM homepet_{$baseId}.financeiropendente f
                LEFT JOIN homepet_{$baseId}.pet p ON f.pet_id = p.id
                LEFT JOIN homepet_{$baseId}.cliente c ON p.dono_id = c.id
                WHERE f.estabelecimento_id = '{$baseId}' AND  DATE(f.data) = :data";

        $stmt = $this->conn->executeQuery($sql, ['data' => $data->format('Y-m-d')]);
        return $stmt->fetchAllAssociative();
    }

    public function confirmarPagamento($baseId, int $id): void
    {
        // Buscar o registro pendente
        $sql = "SELECT * 
            FROM homepet_{$baseId}.financeiropendente 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $registroPendente = $this->conn->executeQuery($sql, ['id' => $id])->fetchAssociative();

        if (!$registroPendente) {
            throw new \Exception('Registro financeiro pendente não encontrado.');
        }

        // Verificar se existe uma venda pendente correspondente
        $sqlVendaPendente = "SELECT id FROM homepet_{$baseId}.venda 
                            WHERE estabelecimento_id = :baseId 
                              AND pet_id = :pet_id 
                              AND status = 'Pendente'
                              AND origem = :origem
                              AND ABS(total - :valor) < 0.01
                            ORDER BY data DESC
                            LIMIT 1";
        
        $vendaExistente = $this->conn->executeQuery($sqlVendaPendente, [
            'baseId' => $baseId,
            'pet_id' => $registroPendente['pet_id'],
            'origem' => $registroPendente['origem'] ?? 'clinica',
            'valor' => $registroPendente['valor']
        ])->fetchOne();

        if ($vendaExistente) {
            // Atualiza a venda existente para 'Paga'
            $sqlUpdate = "UPDATE homepet_{$baseId}.venda 
                         SET status = 'Paga', 
                             metodo_pagamento = :metodo_pagamento,
                             data = NOW()
                         WHERE id = :venda_id AND estabelecimento_id = :baseId";
            
            $this->conn->executeQuery($sqlUpdate, [
                'metodo_pagamento' => $registroPendente['metodo_pagamento'],
                'venda_id' => $vendaExistente,
                'baseId' => $baseId
            ]);
        } else {
            // Se não existe venda pendente, cria uma nova venda com status 'Paga'
            $sqlInsert = "INSERT INTO homepet_{$baseId}.venda 
                          (estabelecimento_id, cliente, total, data, pet_id, metodo_pagamento, origem, status) 
                          VALUES (:estabelecimento_id, :cliente, :total, :data, :pet_id, :metodo_pagamento, :origem, :status)";
            
            // Buscar nome do cliente através do pet
            $sqlCliente = "SELECT c.nome FROM homepet_{$baseId}.pet p 
                           LEFT JOIN homepet_{$baseId}.cliente c ON p.dono_id = c.id 
                           WHERE p.id = :pet_id";
            $clienteNome = $this->conn->executeQuery($sqlCliente, ['pet_id' => $registroPendente['pet_id']])->fetchOne();
            
            $this->conn->executeQuery($sqlInsert, [
                'estabelecimento_id' => $baseId,
                'cliente' => $clienteNome ?: 'Cliente',
                'total' => $registroPendente['valor'],
                'data' => date('Y-m-d H:i:s'),
                'pet_id' => $registroPendente['pet_id'],
                'metodo_pagamento' => $registroPendente['metodo_pagamento'],
                'origem' => $registroPendente['origem'] ?? 'clinica',
                'status' => 'Paga',
            ]);
        }

        // Remover do financeiropendente
        $sqlDelete = "DELETE FROM homepet_{$baseId}.financeiropendente 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $this->conn->executeQuery($sqlDelete, ['id' => $id]);
    }

    public function findPendenteById($baseId, int $id)
    {
        $sql = "SELECT * 
            FROM homepet_{$baseId}.financeiropendente 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $stmt = $this->conn->executeQuery($sql, ['id' => $id]);
        return $stmt->fetchAssociative();
    }

    public function deletePendente($baseId, int $id): void
    {
        $sql = "DELETE FROM homepet_{$baseId}.financeiropendente 
            WHERE estabelecimento_id = '{$baseId}' AND id = :id";

        $this->conn->executeQuery($sql, ['id' => $id]);
    }


    public function verificaServicoExistente($baseId, $agendamentoId){
        $sql = "SELECT id 
            FROM homepet_{$baseId}.financeiropendente 
            WHERE estabelecimento_id = '{$baseId}' AND agendamento_id = $agendamentoId";

        $query = $this->conn->query($sql);
        return $query->fetch();

    }

    public function savePendente($baseId, FinanceiroPendente $financeiro): void
    {
        $descricao = $financeiro->getDescricao();
        $valor = $financeiro->getValor();
        $data = $financeiro->getData()->format('Y-m-d H:i:s');
        $petId = $financeiro->getPetId() ?? null;
        $agendamentoId = $financeiro->getAgendamentoId() ?? null;
        $metodo = $financeiro->getMetodoPagamento() ?? 'pendente';
        $origem = $financeiro->getOrigem() ?? 'clinica';

        $qtdDiarias = method_exists($financeiro, 'getQuantidadeDiarias')
            ? (int) $financeiro->getQuantidadeDiarias()
            : (int) ($_POST['quantidade_diarias'] ?? 1);

        $sql = "INSERT INTO homepet_{$baseId}.financeiropendente 
                (estabelecimento_id, descricao, valor, data, pet_id, metodo_pagamento, agendamento_id, origem) 
                VALUES (:estabelecimento_id, :descricao, :valor, :data, :pet_id, :metodo_pagamento, :agendamento_id, :origem)";

        // Se for internação com várias diárias → insere várias linhas
        if (stripos($descricao, 'internação') !== false && $qtdDiarias > 1) {
            for ($i = 1; $i <= $qtdDiarias; $i++) {
                $desc = "{$descricao} (Diária {$i}/{$qtdDiarias})";
                $this->conn->executeQuery($sql, [
                    'estabelecimento_id' => $baseId,
                    'descricao'          => $desc,
                    'valor'              => $valor,
                    'data'               => $data,
                    'pet_id'             => $petId,
                    'metodo_pagamento'   => $metodo,
                    'agendamento_id'     => $agendamentoId,
                    'origem'             => $origem,
                ]);
            }
            return;
        }

        // caso contrário, insere um registro só
        $this->conn->executeQuery($sql, [
            'estabelecimento_id' => $baseId,
            'descricao'          => $descricao,
            'valor'              => $valor,
            'data'               => $data,
            'pet_id'             => $petId,
            'metodo_pagamento'   => $metodo,
            'agendamento_id'     => $agendamentoId,
            'origem'             => $origem,
        ]);
    }



    public function findByBaseId($baseId, array $criteria): array
    {
        $sql = "SELECT id, descricao, valor, data, pet_id, metodo_pagamento, agendamento_id 
                FROM homepet_{$baseId}.financeiropendente 
                WHERE estabelecimento_id = '{$baseId}' AND agendamento_id = :agendamentoId";
        
        $stmt = $this->conn->executeQuery($sql, [
            'agendamentoId' => $criteria['agendamentoId']
        ]);

        return $stmt->fetchAllAssociative();
    }

    public function removeByBaseId($baseId, $agendamentoId): void
    {
        $sql = "DELETE FROM homepet_{$baseId}.financeiropendente 
                WHERE estabelecimento_id = '{$baseId}' AND agendamento_id = :agendamentoId";
        
        $this->conn->executeQuery($sql, [
            'agendamentoId' => $agendamentoId
        ]);
    }

    public function findByClienteId(int $baseId, int $clienteId): array
    {
        $sql = "SELECT f.descricao, f.valor, f.data
                FROM homepet_{$baseId}.financeiropendente f
                JOIN homepet_{$baseId}.pet p ON f.pet_id = p.id
                WHERE f.estabelecimento_id = :baseId AND p.dono_id = :clienteId
                ORDER BY f.data DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $baseId,
            'clienteId' => $clienteId
        ]);
    }

    public function findAllClinica(int $baseId): array
    {
        $sql = "SELECT f.id, f.descricao, f.valor, f.valor AS total, f.data, f.origem, p.nome AS pet_nome, c.nome AS dono_nome 
            FROM homepet_{$baseId}.financeiropendente f
            LEFT JOIN homepet_{$baseId}.pet p ON f.pet_id = p.id
            LEFT JOIN homepet_{$baseId}.cliente c ON p.dono_id = c.id
            WHERE f.estabelecimento_id = :baseId
              AND (f.status IS NULL OR LOWER(f.status) NOT IN ('inativo', 'pago'))
            ORDER BY f.data DESC";
            
        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $baseId
        ]);

    }

    public function somarDebitosPendentes($baseId): float
    {
        $sql = "SELECT SUM(valor) as total
                FROM homepet_{$baseId}.financeiropendente
                WHERE estabelecimento_id = :baseId
                  AND (status IS NULL OR LOWER(status) NOT IN ('inativo', 'pago'))";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('baseId', $baseId);
        $result = $stmt->executeQuery()->fetchAssociative();

        return (float) ($result['total'] ?? 0);
    }

    public function contarDebitosPendentes($baseId): int
    {
        $sql = "SELECT COUNT(*)
                FROM homepet_{$baseId}.financeiropendente
                WHERE estabelecimento_id = :baseId
                  AND (status IS NULL OR LOWER(status) NOT IN ('inativo', 'pago'))";

        return (int) $this->conn->fetchOne($sql, ['baseId' => $baseId]);
    }

    public function inativar($baseId, int $id): void
    {
        $sql = "UPDATE homepet_{$baseId}.financeiropendente 
                SET status = 'inativo'
                WHERE estabelecimento_id = :baseId AND id = :id";

        $this->conn->executeQuery($sql, [
            'baseId' => $baseId,
            'id'     => $id
        ]);
    }


    public function findInativos(int $baseId, int $petId): array
    {
        $sql = "SELECT * 
                FROM homepet_{$baseId}.financeiropendente
                WHERE estabelecimento_id = :baseId
                  AND pet_id = :petId
                  AND status = 'inativo'
                ORDER BY data DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $baseId,
            'petId' => $petId
        ]);
    }

    public function findAtivosPorPet(int $baseId, int $petId): array
    {
        $sql = "SELECT * 
                FROM homepet_{$baseId}.financeiropendente 
                WHERE estabelecimento_id = :baseId 
                  AND pet_id = :petId
                  AND (status IS NULL OR status != 'inativo')
                ORDER BY data DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $baseId,
            'petId' => $petId
        ]);
    }

    public function findInativosPorPet(int $baseId, int $petId): array
    {
        $sql = "SELECT * 
                FROM homepet_{$baseId}.financeiropendente 
                WHERE estabelecimento_id = :baseId 
                  AND pet_id = :petId
                  AND status = 'inativo'
                ORDER BY data DESC";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId' => $baseId,
            'petId' => $petId
        ]);
    }




    /**
     * Insere lançamento pendente originado de venda da clínica.
     * Substitui o INSERT inline que existia no VendaController::concluirVenda.
     */
    public function inserirVendaClinica(int $baseId, string $descricao, float $valor, ?int $vendaId = null): void
    {
        $sql = "INSERT INTO homepet_{$baseId}.financeiropendente
                    (descricao, valor, data, metodo_pagamento, origem, status,
                     estabelecimento_id, inativar, venda_id)
                VALUES
                    (:descricao, :valor, NOW(), 'pendente', 'clinica', 'Pendente',
                     :estabelecimento_id, 0, :venda_id)";

        $this->conn->executeStatement($sql, [
            'descricao'          => $descricao,
            'valor'              => $valor,
            'estabelecimento_id' => $baseId,
            'venda_id'           => $vendaId,
        ]);
    }

    /**
     * Insere lançamento pendente originado de venda PDV.
     */
    public function inserirPdv(
        int $estabelecimentoId,
        string $nomeCliente,
        float $total,
        ?int $petId,
        ?int $vendaId = null
    ): void {
        // Antes usava {$baseId}, variável inexistente neste método (o parâmetro é $estabelecimentoId)
        $sql = "INSERT INTO homepet_{$estabelecimentoId}.financeiropendente
                    (estabelecimento_id, descricao, valor, data, metodo_pagamento, origem, pet_id, venda_id)
                VALUES
                    (:estab, :descricao, :valor, NOW(), 'pendente', 'PDV', :pet_id, :venda_id)";

        $this->conn->executeStatement($sql, [
            'estab'     => $estabelecimentoId,
            'descricao' => "Venda PDV — {$nomeCliente}",
            'valor'     => $total,
            'pet_id'    => $petId,
            'venda_id'  => $vendaId,
        ]);
    }

    /**
     * Tira do financeiro pendente o lançamento de uma venda que acabou de ser
     * finalizada no PDV (status 'pago' — mesmo critério de marcarPagos(), então
     * some das listagens de pendentes mas o histórico fica preservado).
     *
     * 1) Caminho normal: lançamentos ligados à venda por venda_id.
     * 2) Legado: lançamentos criados antes da coluna venda_id existir. Casa por
     *    valor + pet (pet_id ou "(#petId)" na descrição, que é como a clínica grava)
     *    e baixa no máximo UM, o mais recente, para não levar outro lançamento junto.
     *
     * @return int quantidade de lançamentos baixados
     */
    public function baixarPorVenda(int $baseId, int $vendaId, float $total, ?int $petId = null): int
    {
        $aberto = "(status IS NULL OR LOWER(status) NOT IN ('inativo', 'pago'))";

        $afetadas = (int) $this->conn->executeStatement(
            "UPDATE homepet_{$baseId}.financeiropendente
                SET status = 'pago'
              WHERE estabelecimento_id = :baseId
                AND venda_id = :vendaId
                AND {$aberto}",
            ['baseId' => $baseId, 'vendaId' => $vendaId]
        );

        if ($afetadas > 0 || $petId === null) {
            return $afetadas;
        }

        return (int) $this->conn->executeStatement(
            "UPDATE homepet_{$baseId}.financeiropendente
                SET status = 'pago'
              WHERE estabelecimento_id = :baseId
                AND venda_id IS NULL
                AND metodo_pagamento = 'pendente'
                AND ABS(valor - :total) < 0.01
                AND {$aberto}
                AND (pet_id = :petId OR descricao LIKE :descLike)
              ORDER BY id DESC
              LIMIT 1",
            [
                'baseId'   => $baseId,
                'total'    => $total,
                'petId'    => $petId,
                'descLike' => '%(#' . $petId . ')',
            ]
        );
    }

    public function buscaInativosPendentes($baseId)
    {
        $sql = "SELECT fp.id, fp.descricao, fp.valor AS total, fp.data, fp.pet_id,
                p.nome AS pet_nome, c.nome AS dono_nome, 
                COALESCE(fp.metodo_pagamento, 'pendente') AS metodo_pagamento,
                'financeiro_pendente' AS tipo
            FROM homepet_{$baseId}.financeiropendente fp
            LEFT JOIN homepet_{$baseId}.pet p ON fp.pet_id = p.id
            LEFT JOIN homepet_{$baseId}.cliente c ON p.dono_id = c.id
                WHERE fp.estabelecimento_id = {$baseId}
            AND fp.status = 'inativo'
            ORDER BY fp.data DESC";

        $query = $this->conn->executeQuery($sql);

        return $query->fetchAllAssociative();

    }

    /**
     * Atendimentos/serviços em aberto (não pagos) de um cliente, com o pet,
     * para agrupar por pet na venda.
     */
    public function findAbertosPorCliente(int $baseId, int $clienteId): array
    {
        $sql = "SELECT f.id, f.descricao, f.valor, f.pet_id, p.nome AS pet_nome
                FROM homepet_{$baseId}.financeiropendente f
                JOIN homepet_{$baseId}.pet p ON f.pet_id = p.id
                WHERE f.estabelecimento_id = :baseId
                  AND p.dono_id = :clienteId
                  AND (f.status IS NULL OR f.status NOT IN ('inativo','pago'))
                  -- Saldo de pagamento parcial NÃO entra como item de uma nova venda
                  -- (seria cobrado duas vezes); ele é recebido por receberSaldo().
                  AND (f.origem IS NULL OR f.origem <> 'SALDO_PDV')
                ORDER BY p.nome ASC, f.id ASC";

        return $this->conn->fetchAllAssociative($sql, [
            'baseId'    => $baseId,
            'clienteId' => $clienteId,
        ]);
    }

    /**
     * Marca pendências como pagas (baixa após serem incluídas numa venda).
     */
    public function marcarPagos(int $baseId, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (empty($ids)) {
            return;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE homepet_{$baseId}.financeiropendente
                SET status = 'pago'
                WHERE estabelecimento_id = ? AND id IN ($in)";
        $params = array_merge([$baseId], $ids);
        $this->conn->executeStatement($sql, $params);
    }

    // =====================================================================
    // PAGAMENTO PARCIAL (FIADO): o que sobra de uma venda vira débito do cliente
    // =====================================================================

    /** Marca o lançamento de financeiropendente que representa o saldo de um pagamento parcial. */
    public const ORIGEM_SALDO = 'SALDO_PDV';

    /**
     * Lança o saldo devedor de uma venda paga parcialmente. A venda em si NÃO é
     * alterada: continua finalizada pelo valor cheio; só o que faltou receber
     * fica em aberto aqui, no financeiro pendente do cliente (via pet).
     *
     * Idempotente: se a venda já tem saldo em aberto, devolve o id existente.
     */
    public function inserirSaldoPdv(int $baseId, string $nomeCliente, float $valor, int $petId, int $vendaId): int
    {
        $valor = round($valor, 2);

        $existente = $this->conn->fetchOne(
            "SELECT id FROM homepet_{$baseId}.financeiropendente
             WHERE estabelecimento_id = :b AND venda_id = :v AND origem = :o
               AND (status IS NULL OR LOWER(status) NOT IN ('pago','inativo'))
             LIMIT 1",
            ['b' => $baseId, 'v' => $vendaId, 'o' => self::ORIGEM_SALDO]
        );
        if ($existente) {
            return (int) $existente;
        }

        $descricao = mb_substr(sprintf(
            'Saldo devedor de R$ %s — Venda #%d — %s',
            number_format($valor, 2, ',', '.'),
            $vendaId,
            $nomeCliente
        ), 0, 255);

        $this->conn->executeStatement(
            "INSERT INTO homepet_{$baseId}.financeiropendente
                (estabelecimento_id, descricao, valor, data, pet_id, metodo_pagamento, origem, status, venda_id)
             VALUES (:b, :d, :v, NOW(), :pet, 'pendente', :o, 'Pendente', :venda)",
            ['b' => $baseId, 'd' => $descricao, 'v' => $valor, 'pet' => $petId, 'o' => self::ORIGEM_SALDO, 'venda' => $vendaId]
        );

        return (int) $this->conn->lastInsertId();
    }

    /** Débitos (saldo de pagamento parcial) em aberto de um cliente, somando todos os pets dele. */
    public function findSaldosAbertosPorCliente(int $baseId, int $clienteId): array
    {
        return $this->conn->fetchAllAssociative(
            "SELECT f.id, f.descricao, f.valor, f.data, f.venda_id, f.pet_id, p.nome AS pet_nome
             FROM homepet_{$baseId}.financeiropendente f
             JOIN homepet_{$baseId}.pet p ON p.id = f.pet_id
             WHERE f.estabelecimento_id = :b AND p.dono_id = :c AND f.origem = :o
               AND (f.status IS NULL OR LOWER(f.status) NOT IN ('pago','inativo'))
             ORDER BY f.data ASC, f.id ASC",
            ['b' => $baseId, 'c' => $clienteId, 'o' => self::ORIGEM_SALDO]
        );
    }

    /**
     * Recebe um valor (parcial ou total) de um débito. Em uma única transação:
     *  1. registra a entrada no financeiro (dinheiro que entrou agora);
     *  2. registra a forma de pagamento na venda de origem;
     *  3. abate o débito — se zerou, ele sai do pendente (status 'pago': fica no
     *     histórico, como as demais baixas); se não, continua em aberto com o novo saldo.
     * Não cria uma nova venda: o faturamento da venda de origem já foi contado.
     *
     * @return array{quitado:bool, restante:float, cliente:string, pago:float}
     */
    public function receberSaldo(int $baseId, int $id, float $valor, string $metodo, ?string $bandeira = null): array
    {
        if (!in_array($metodo, ['dinheiro', 'pix', 'credito', 'debito'], true)) {
            throw new \InvalidArgumentException('Forma de pagamento inválida.');
        }

        $valor = round($valor, 2);
        if ($valor <= 0) {
            throw new \InvalidArgumentException('Informe um valor maior que zero.');
        }

        $this->conn->beginTransaction();
        try {
            $row = $this->conn->fetchAssociative(
                "SELECT id, valor, venda_id, pet_id, descricao
                 FROM homepet_{$baseId}.financeiropendente
                 WHERE estabelecimento_id = :b AND id = :id AND origem = :o
                   AND (status IS NULL OR LOWER(status) NOT IN ('pago','inativo'))
                 FOR UPDATE",
                ['b' => $baseId, 'id' => $id, 'o' => self::ORIGEM_SALDO]
            );
            if (!$row) {
                throw new \RuntimeException('Débito não encontrado ou já quitado.');
            }

            $restante = round((float) $row['valor'], 2);
            if ($valor - $restante > 0.009) {
                throw new \InvalidArgumentException(sprintf(
                    'O valor informado (R$ %s) é maior que o débito (R$ %s).',
                    number_format($valor, 2, ',', '.'),
                    number_format($restante, 2, ',', '.')
                ));
            }

            $nomeCliente = (string) ($this->conn->fetchOne(
                "SELECT c.nome FROM homepet_{$baseId}.pet p
                 LEFT JOIN homepet_{$baseId}.cliente c ON c.id = p.dono_id WHERE p.id = :pet",
                ['pet' => $row['pet_id']]
            ) ?: 'Cliente');

            $vendaRef = !empty($row['venda_id']) ? ' — Venda #' . (int) $row['venda_id'] : '';

            // 1) entrada no financeiro
            $this->conn->executeStatement(
                "INSERT INTO homepet_{$baseId}.financeiro
                    (estabelecimento_id, descricao, valor, data, metodo_pagamento, tipo, status, origem, pet_id)
                 VALUES (:b, :d, :v, NOW(), :m, 'ENTRADA', 'Pago', 'PDV', :pet)",
                ['b' => $baseId, 'd' => mb_substr('Recebimento de saldo devedor' . $vendaRef . ' — ' . $nomeCliente, 0, 255),
                 'v' => $valor, 'm' => $metodo, 'pet' => $row['pet_id']]
            );

            // 2) forma de pagamento na venda de origem
            if (!empty($row['venda_id'])) {
                $this->conn->executeStatement(
                    "INSERT INTO homepet_{$baseId}.venda_pagamento
                        (estabelecimento_id, venda_id, metodo, valor, bandeira_cartao, parcelas, data)
                     VALUES (:b, :venda, :m, :v, :band, NULL, NOW())",
                    ['b' => $baseId, 'venda' => (int) $row['venda_id'], 'm' => $metodo, 'v' => $valor, 'band' => $bandeira]
                );
            }

            // 3) abate / quita
            $novoRestante = round($restante - $valor, 2);
            $quitado = $novoRestante <= 0.009;
            if ($quitado) {
                $this->conn->executeStatement(
                    "UPDATE homepet_{$baseId}.financeiropendente
                     SET status = 'pago', descricao = LEFT(CONCAT(descricao, ' — quitado em ', DATE_FORMAT(NOW(), '%d/%m/%Y')), 255)
                     WHERE id = :id AND estabelecimento_id = :b",
                    ['id' => $id, 'b' => $baseId]
                );
                $novoRestante = 0.0;
            } else {
                $this->conn->executeStatement(
                    "UPDATE homepet_{$baseId}.financeiropendente SET valor = :v WHERE id = :id AND estabelecimento_id = :b",
                    ['v' => $novoRestante, 'id' => $id, 'b' => $baseId]
                );
            }

            $this->conn->commit();

            return ['quitado' => $quitado, 'restante' => $novoRestante, 'cliente' => $nomeCliente, 'pago' => $valor];
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}
