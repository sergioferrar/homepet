<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Criação da tabela assinatura_modulo.
 *
 * Relaciona cada estabelecimento com os módulos adicionais
 * (Banho & Tosa, Hospedagem, Clínica, PDV/NF-e) que ele contratou
 * além do plano base. Segura para dados existentes — não altera
 * nenhuma tabela existente, apenas cria a nova.
 *
 * Como rodar (sem perder dados):
 *   php bin/console doctrine:migrations:migrate --no-interaction
 */
final class Version20260228145037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela assinatura_modulo para módulos adicionais por estabelecimento';
    }

    public function up(Schema $schema): void
    {
        // Verifica se a tabela já existe antes de criar (idempotente)
        $this->addSql('
            CREATE TABLE IF NOT EXISTS `assinatura_modulo` (
                `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
                `estabelecimento_id`  INT(11)       NOT NULL
                    COMMENT \'FK para estabelecimento.id\',
                `modulo_id`           INT(11)       NOT NULL
                    COMMENT \'FK para modulo.id\',
                `modulo_titulo`       VARCHAR(255)  NOT NULL
                    COMMENT \'Snapshot do título no momento da contratação\',
                `valor_mensal`        DECIMAL(10,2) NOT NULL
                    COMMENT \'Valor adicional mensal cobrado por este módulo\',
                `status`              VARCHAR(20)   NOT NULL DEFAULT \'pendente\'
                    COMMENT \'ativo | pendente | cancelado | suspenso\',
                `subscription_id`     VARCHAR(255)  NULL
                    COMMENT \'preapproval_id do Mercado Pago\',
                `init_point`          VARCHAR(500)  NULL
                    COMMENT \'URL de aprovação gerada pelo gateway\',
                `contratado_em`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `cancelado_em`        DATETIME      NULL,
                `proxima_cobranca`    DATETIME      NULL,
                `observacoes`         LONGTEXT      NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_am_estabelecimento` (`estabelecimento_id`),
                INDEX `idx_am_modulo`          (`modulo_id`),
                INDEX `idx_am_status`          (`status`),
                INDEX `idx_am_subscription`    (`subscription_id`)
            ) ENGINE=InnoDB
              DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              COMMENT=\'Módulos adicionais contratados por estabelecimento\'
        ');
    }

    public function down(Schema $schema): void
    {
        // Remove a tabela — dados de assinaturas de módulos adicionais serão perdidos.
        // Só execute o rollback se tiver certeza que pode descartar esses registros.
        $this->addSql('DROP TABLE IF EXISTS `assinatura_modulo`');
    }

    /**
     * Impede que o Doctrine aborte a migration em caso de warnings
     * (ex.: tabela já existir por IF NOT EXISTS).
     */
    public function isTransactional(): bool
    {
        return false;
    }
}
