<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * DynamicConnectionManager
 *
 * Gerencia a estratégia de conexão multi-tenant da aplicação.
 *
 * Dois modos de operação:
 *
 *  MODO 1 — MULTI_DATABASE (padrão futuro)
 *    Cada tenant possui seu próprio banco de dados.
 *    O manager troca fisicamente a conexão para o banco do tenant.
 *    Ativado via: $_ENV['TENANT_STRATEGY'] = 'multi_database'
 *
 *  MODO 2 — SINGLE_DATABASE (padrão atual / fallback)
 *    Todos os tenants compartilham o banco homepet_1.
 *    O isolamento é feito por colunas estabelecimento_id em todas as tabelas.
 *    Não há troca de conexão; apenas o estabelecimento_id corrente é armazenado
 *    para ser usado pelos repositórios/queries como filtro automático.
 *    Ativado via: $_ENV['TENANT_STRATEGY'] = 'single_database'  (ou ausente)
 */
class DynamicConnectionManager
{
    // -----------------------------------------------------------------------
    // Constantes de estratégia
    // -----------------------------------------------------------------------

    public const STRATEGY_MULTI_DATABASE  = 'multi_database';
    public const STRATEGY_SINGLE_DATABASE = 'single_database';

    /**
     * Banco padrão compartilhado usado no modo single_database.
     */
    private const DEFAULT_DATABASE = 'homepet_1';

    // -----------------------------------------------------------------------
    // Estado interno
    // -----------------------------------------------------------------------

    private Connection $connection;
    private array $originalParams;

    /**
     * Estratégia ativa: 'multi_database' | 'single_database'
     */
    private string $strategy;

    /**
     * Armazena o estabelecimento_id corrente no modo single_database,
     * para que repositórios possam utilizá-lo como filtro de tenant.
     */
    private ?int $currentEstabelecimentoId = null;

    /**
     * Banco/tenant ativos no momento (útil para logs e debug).
     */
    private ?string $currentTenant = null;

    // -----------------------------------------------------------------------
    // Construtor
    // -----------------------------------------------------------------------

    public function __construct(ManagerRegistry $registry)
    {
        $this->connection     = $registry->getConnection();
        $this->originalParams = $this->connection->getParams();
        $this->strategy       = $this->resolveStrategy();
    }

    // -----------------------------------------------------------------------
    // API pública principal
    // -----------------------------------------------------------------------

    /**
     * Configura o contexto do tenant conforme a estratégia ativa.
     *
     * @param string   $dbNameOrTenant  Nome do banco (multi_database) ou
     *                                  qualquer identificador descritivo (single_database).
     * @param int|null $estabelecimentoId  Obrigatório no modo single_database.
     * @param string   $username           Opcional; usado em ambientes onde o
     *                                     usuário MySQL varia por tenant.
     */
    public function setTenant(
        string $dbNameOrTenant,
        ?int   $estabelecimentoId = null,
        string $username = ''
    ): void {
        $this->currentTenant = $dbNameOrTenant;

        if ($this->isMultiDatabaseMode()) {
            $this->switchDatabase($dbNameOrTenant, $username);
        } else {
            $this->setEstabelecimentoId($estabelecimentoId);
        }
    }

    /**
     * Restaura o contexto padrão (banco original + sem filtro de tenant).
     */
    public function restoreTenant(): void
    {
        $this->currentTenant           = null;
        $this->currentEstabelecimentoId = null;

        if ($this->isMultiDatabaseMode()) {
            $this->restoreOriginalConnection();
        }
        // No modo single_database não há conexão a restaurar;
        // limpar o estabelecimento_id já é suficiente.
    }

    // -----------------------------------------------------------------------
    // Modo MULTI_DATABASE
    // -----------------------------------------------------------------------

    /**
     * Troca fisicamente a conexão DBAL para outro banco de dados.
     * Usado internamente no modo multi_database.
     *
     * @throws \RuntimeException se a estratégia atual não for multi_database.
     */
    public function switchDatabase(string $newDbName, string $newUsername = ''): void
    {
        if (!$this->isMultiDatabaseMode()) {
            throw new \RuntimeException(
                'switchDatabase() só pode ser chamado no modo multi_database. '
                . 'Use setEstabelecimentoId() para o modo single_database.'
            );
        }

        if ($this->connection->isConnected()) {
            $this->connection->close();
        }

        $params           = $this->originalParams;
        $params['dbname'] = $newDbName ?: ($_ENV['DBNAMETENANT'] ?? self::DEFAULT_DATABASE);

        // Troca de usuário MySQL apenas quando aplicável ao ambiente
        if (!empty($newUsername) && get_current_user() === ($_ENV['DB_SYSTEM_USER'] ?? '')) {
            $params['user'] = $newUsername;
        }

        $this->applyConnectionParams($params);
        $this->connection->connect();
    }

    /**
     * Restaura a conexão para o banco definido em DATABASE_URL.
     * Usado internamente no modo multi_database.
     */
    public function restoreOriginalConnection(): void
    {
        $parsed   = $this->parseDatabaseUrl();
        $this->switchDatabase($parsed['dbname'], $parsed['user']);
    }

    // -----------------------------------------------------------------------
    // Modo SINGLE_DATABASE
    // -----------------------------------------------------------------------

    /**
     * Define o estabelecimento_id corrente para filtro de tenant.
     * A conexão permanece no banco padrão homepet_1.
     */
    public function setEstabelecimentoId(?int $id): void
    {
        $this->currentEstabelecimentoId = $id;
    }

    /**
     * Retorna o estabelecimento_id corrente.
     * Retorna null quando nenhum tenant está ativo (ex.: super admin sem filtro).
     */
    public function getCurrentEstabelecimentoId(): ?int
    {
        return $this->currentEstabelecimentoId;
    }

    /**
     * Indica se há um filtro de tenant ativo no modo single_database.
     * Super admins podem operar sem filtro (retorna false).
     */
    public function hasTenantFilter(): bool
    {
        return $this->currentEstabelecimentoId !== null;
    }

    // -----------------------------------------------------------------------
    // Informações e utilitários
    // -----------------------------------------------------------------------

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getStrategy(): string
    {
        return $this->strategy;
    }

    public function isMultiDatabaseMode(): bool
    {
        return $this->strategy === self::STRATEGY_MULTI_DATABASE;
    }

    public function isSingleDatabaseMode(): bool
    {
        return $this->strategy === self::STRATEGY_SINGLE_DATABASE;
    }

    public function getCurrentTenant(): ?string
    {
        return $this->currentTenant;
    }

    /**
     * Resumo do estado atual — útil para logs/debug.
     */
    public function getStatus(): array
    {
        return [
            'strategy'              => $this->strategy,
            'current_tenant'        => $this->currentTenant,
            'estabelecimento_id'    => $this->currentEstabelecimentoId,
            'has_tenant_filter'     => $this->hasTenantFilter(),
            'connection_database'   => $this->connection->getDatabase(),
            'is_connected'          => $this->connection->isConnected(),
        ];
    }

    // -----------------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------------

    /**
     * Resolve a estratégia a partir da variável de ambiente TENANT_STRATEGY.
     * Padrão: single_database (compatível com o estado atual da aplicação).
     */
    private function resolveStrategy(): string
    {
        $env = strtolower(trim($_ENV['TENANT_STRATEGY'] ?? ''));

        return $env === self::STRATEGY_MULTI_DATABASE
            ? self::STRATEGY_MULTI_DATABASE
            : self::STRATEGY_SINGLE_DATABASE;
    }

    /**
     * Aplica novos parâmetros à conexão DBAL via Reflection
     * (necessário porque a Connection não expõe setter público para params).
     */
    private function applyConnectionParams(array $params): void
    {
        $reflection = new \ReflectionClass($this->connection);
        $property   = $reflection->getProperty('params');
        $property->setAccessible(true);
        $property->setValue($this->connection, $params);
    }

    /**
     * Extrai dbname e user da DATABASE_URL definida no .env.
     * Formato esperado: mysql://user:pass@host/dbname?serverVersion=...
     */
    private function parseDatabaseUrl(): array
    {
        $url   = $_ENV['DATABASE_URL'] ?? '';
        $parts = parse_url($url);

        return [
            'dbname' => ltrim($parts['path'] ?? ('/' . self::DEFAULT_DATABASE), '/'),
            'user'   => $parts['user'] ?? '',
        ];
    }
}