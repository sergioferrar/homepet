<?php

namespace App\Tests\Integration;

use App\Installer\TenantDatabaseInstaller;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base dos testes de integração da clínica.
 *
 * Cria um banco de tenant descartável (homepet_999999), instala o schema com
 * o TenantDatabaseInstaller e semeia um cenário mínimo: 1 tutor, 1 pet,
 * 2 consultas, 2 serviços e 1 produto.
 *
 * ── Como rodar ───────────────────────────────────────────────────────────────
 * Exige MySQL/MariaDB acessível e um usuário com permissão de CREATE DATABASE.
 *
 *   DATABASE_URL="mysql://root:senha@127.0.0.1:3306/homepet" \
 *   php bin/phpunit --testsuite integracao
 *
 * Sem banco acessível a suíte é PULADA (não falha), para não travar CI que só
 * roda os testes unitários.
 */
abstract class TenantIntegrationTestCase extends KernelTestCase
{
    /** ID do estabelecimento fictício — o banco vira homepet_999999. */
    protected const TENANT_ID = 999999;

    /** Um segundo tenant, para provar isolamento entre estabelecimentos. */
    protected const TENANT_VIZINHO_ID = 999998;

    protected static ?Connection $admin = null;

    /** IDs semeados, preenchidos por semear(). */
    protected array $fixtures = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::bootKernel();

        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $params = $registry->getConnection()->getParams();

        // Conexão "administrativa": sem dbname, para poder criar/dropar bases.
        unset($params['dbname'], $params['url'], $params['path']);

        try {
            self::$admin = DriverManager::getConnection($params);
            self::$admin->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            self::$admin = null;
            self::markTestSkipped(
                'Banco indisponível para testes de integração (' . $e->getMessage() . '). '
                . 'Configure DATABASE_URL com um usuário que possa criar bases.'
            );
        }

        foreach ([self::TENANT_ID, self::TENANT_VIZINHO_ID] as $tenantId) {
            self::criarTenant($tenantId);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$admin !== null) {
            foreach ([self::TENANT_ID, self::TENANT_VIZINHO_ID] as $tenantId) {
                self::$admin->executeStatement(
                    sprintf('DROP DATABASE IF EXISTS `homepet_%d`', $tenantId)
                );
            }
            self::$admin->close();
            self::$admin = null;
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! static::$booted) {
            self::bootKernel();
        }

        $this->limpar();
        $this->fixtures = $this->semear();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Infraestrutura
    // ═════════════════════════════════════════════════════════════════════════

    private static function criarTenant(int $tenantId): void
    {
        $banco = sprintf('homepet_%d', $tenantId);

        self::$admin->executeStatement(sprintf('DROP DATABASE IF EXISTS `%s`', $banco));
        self::$admin->executeStatement(
            sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci', $banco)
        );

        $params = self::$admin->getParams();
        $params['dbname'] = $banco;

        $conexaoTenant = DriverManager::getConnection($params);
        $resultado = (new TenantDatabaseInstaller())->install($conexaoTenant);

        if (($resultado['success'] ?? false) !== true) {
            $conexaoTenant->close();
            self::fail(sprintf(
                'Falha ao instalar o schema do tenant %s: tabela "%s" — %s',
                $banco,
                $resultado['failed_table'] ?? '?',
                $resultado['message'] ?? implode('; ', $resultado['errors'] ?? []),
            ));
        }

        $conexaoTenant->close();
    }

    /** Conexão da aplicação (a mesma usada pelos repositories). */
    protected function conn(): Connection
    {
        return static::getContainer()->get('doctrine')->getConnection();
    }

    protected function tabela(string $nome, int $tenantId = self::TENANT_ID): string
    {
        return sprintf('homepet_%d.%s', $tenantId, $nome);
    }

    private function limpar(): void
    {
        $tabelas = [
            'venda_item', 'venda', 'estoque_movimento', 'financeiro',
            'financeiro_pendente', 'consulta', 'produto', 'servico',
            'pet', 'cliente', 'veterinario',
        ];

        foreach ([self::TENANT_ID, self::TENANT_VIZINHO_ID] as $tenantId) {
            $this->conn()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($tabelas as $tabela) {
                try {
                    $this->conn()->executeStatement('TRUNCATE TABLE ' . $this->tabela($tabela, $tenantId));
                } catch (\Throwable) {
                    // tabela opcional que não existe neste schema — segue
                }
            }

            $this->conn()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Fixtures
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Cenário mínimo da clínica.
     *
     * Os valores (produto R$ 45 / diária R$ 80) reproduzem o caso do bug
     * relatado, para os testes de venda ficarem legíveis.
     *
     * @return array<string, int>
     */
    protected function semear(): array
    {
        $conn = $this->conn();
        $estab = self::TENANT_ID;

        $conn->insert($this->tabela('veterinario'), [
            'nome' => 'Dra. Ana Prado', 'crmv' => 'CRMV-MG 12345',
            'estabelecimento_id' => $estab, 'status' => 'ativo',
        ]);
        $vetId = (int) $conn->lastInsertId();

        $conn->insert($this->tabela('cliente'), [
            'estabelecimento_id' => $estab, 'nome' => 'João da Silva',
            'telefone' => '31999990000', 'email' => 'joao@example.com',
        ]);
        $clienteId = (int) $conn->lastInsertId();

        $conn->insert($this->tabela('pet'), [
            'estabelecimento_id' => $estab, 'nome' => 'Rex', 'especie' => 'Canino',
            'raca' => 'SRD', 'sexo' => 'M', 'idade' => '4', 'dono_id' => (string) $clienteId,
        ]);
        $petId = (int) $conn->lastInsertId();

        // Duas consultas em dias diferentes — base da navegação por atendimento
        $conn->insert($this->tabela('consulta'), [
            'estabelecimento_id' => $estab, 'cliente_id' => $clienteId, 'pet_id' => $petId,
            'data' => '2026-07-20', 'hora' => '09:00:00', 'status' => 'atendido',
            'tipo' => 'Consulta', 'veterinario_id' => $vetId,
        ]);
        $consultaAId = (int) $conn->lastInsertId();

        $conn->insert($this->tabela('consulta'), [
            'estabelecimento_id' => $estab, 'cliente_id' => $clienteId, 'pet_id' => $petId,
            'data' => '2026-07-22', 'hora' => '14:30:00', 'status' => 'atendido',
            'tipo' => 'Retorno', 'veterinario_id' => $vetId,
        ]);
        $consultaBId = (int) $conn->lastInsertId();

        $conn->insert($this->tabela('servico'), [
            'estabelecimento_id' => $estab, 'nome' => 'Internação / Diária',
            'descricao' => 'Diária de internação', 'valor' => 80, 'tipo' => 'clinica',
        ]);
        $servicoDiariaId = (int) $conn->lastInsertId();

        $conn->insert($this->tabela('servico'), [
            'estabelecimento_id' => $estab, 'nome' => 'Consulta Clínica',
            'descricao' => 'Atendimento clínico', 'valor' => 150, 'tipo' => 'clinica',
        ]);
        $servicoConsultaId = (int) $conn->lastInsertId();

        $conn->insert($this->tabela('produto'), [
            'estabelecimento_id' => $estab, 'nome' => 'Antipulgas 10kg',
            'preco_custo' => 25.00, 'preco_venda' => 45.00, 'estoque_atual' => 10,
        ]);
        $produtoId = (int) $conn->lastInsertId();

        return [
            'vet_id'             => $vetId,
            'cliente_id'         => $clienteId,
            'pet_id'             => $petId,
            'consulta_a_id'      => $consultaAId,
            'consulta_b_id'      => $consultaBId,
            'servico_diaria_id'  => $servicoDiariaId,
            'servico_consulta_id'=> $servicoConsultaId,
            'produto_id'         => $produtoId,
        ];
    }

    /**
     * Cria uma venda direto no banco, para montar cenários de leitura.
     *
     * @param array<int, array{ref: string, qtd: int, unit: float, desconto?: float}> $itens
     */
    protected function criarVenda(
        ?int $consultaId,
        string $status = 'Paga',
        array $itens = [],
        int $tenantId = self::TENANT_ID,
        ?int $petId = null,
    ): int {
        $conn = $this->conn();
        $total = 0.0;

        $conn->insert($this->tabela('venda', $tenantId), [
            'estabelecimento_id' => $tenantId,
            'cliente'            => 'João da Silva',
            'pet_id'             => $petId ?? $this->fixtures['pet_id'],
            'consulta_id'        => $consultaId,
            'total'              => 0,
            'metodo_pagamento'   => 'pix',
            'origem'             => 'clinica',
            'status'             => $status,
            'data'               => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
        $vendaId = (int) $conn->lastInsertId();

        foreach ($itens as $item) {
            $desconto = $item['desconto'] ?? 0.0;
            $subtotal = round(($item['unit'] * $item['qtd']) - $desconto, 2);
            $total += $subtotal;

            $conn->insert($this->tabela('venda_item', $tenantId), [
                'venda_id'       => $vendaId,
                'tipo'           => str_starts_with($item['ref'], 'P-') ? 'produto' : 'servico',
                'produto_id'     => (int) substr($item['ref'], 2),
                'produto'        => $item['nome'] ?? 'Item',
                'quantidade'     => $item['qtd'],
                'valor_unitario' => $item['unit'],
                'subtotal'       => $subtotal,
            ]);
        }

        $conn->update($this->tabela('venda', $tenantId), ['total' => $total], ['id' => $vendaId]);

        return $vendaId;
    }

    protected function statusDaVenda(int $vendaId, int $tenantId = self::TENANT_ID): ?string
    {
        $status = $this->conn()->fetchOne(
            'SELECT status FROM ' . $this->tabela('venda', $tenantId) . ' WHERE id = ?',
            [$vendaId]
        );

        return $status === false ? null : (string) $status;
    }
}
