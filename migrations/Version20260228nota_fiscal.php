<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Criação da tabela nota_fiscal para integração com Asaas NFS-e.
 *
 * Como rodar sem perder dados:
 *   php bin/console doctrine:migrations:migrate --no-interaction
 */
final class Version20260228nota_fiscal extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela nota_fiscal para rastreamento de NFS-e emitidas via Asaas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS `nota_fiscal` (
                `id`                     INT(11)       NOT NULL AUTO_INCREMENT,
                `estabelecimento_id`     INT(11)       NOT NULL,
                `origem`                 VARCHAR(20)   NOT NULL DEFAULT \'venda\'
                    COMMENT \'venda | servico | avulsa\',
                `venda_id`               INT(11)       NULL,
                `cliente_id`             INT(11)       NULL,
                `cliente_nome`           VARCHAR(255)  NOT NULL,
                `cliente_cpf_cnpj`       VARCHAR(20)   NULL,
                `asaas_customer_id`      VARCHAR(100)  NULL
                    COMMENT \'cus_XXXXX no Asaas\',
                `asaas_invoice_id`       VARCHAR(100)  NULL
                    COMMENT \'inv_XXXXX no Asaas\',
                `valor`                  DECIMAL(10,2) NOT NULL,
                `deducoes`               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `status`                 VARCHAR(30)   NOT NULL DEFAULT \'PENDING\',
                `descricao_servico`      LONGTEXT      NOT NULL,
                `data_emissao`           DATE          NOT NULL,
                `numero_nota`            VARCHAR(50)   NULL,
                `rps_numero`             VARCHAR(50)   NULL,
                `pdf_url`                VARCHAR(500)  NULL,
                `xml_url`                VARCHAR(500)  NULL,
                `municipal_service_id`   VARCHAR(100)  NULL,
                `municipal_service_code` VARCHAR(50)   NULL,
                `municipal_service_name` VARCHAR(255)  NULL,
                `impostos_json`          LONGTEXT      NULL,
                `observacoes`            LONGTEXT      NULL,
                `criado_em`              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `atualizado_em`          DATETIME      NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_nf_estab`          (`estabelecimento_id`),
                INDEX `idx_nf_venda`          (`venda_id`),
                INDEX `idx_nf_asaas_invoice`  (`asaas_invoice_id`),
                INDEX `idx_nf_status`         (`status`),
                INDEX `idx_nf_data_emissao`   (`data_emissao`)
            ) ENGINE=InnoDB
              DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              COMMENT=\'Notas Fiscais de Serviço emitidas via Asaas\'
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS `nota_fiscal`');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
