<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250821131804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE box (id INT AUTO_INCREMENT NOT NULL, pet_id INT DEFAULT NULL, numero SMALLINT UNSIGNED NOT NULL, ocupado TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_8A9483A966F7FB6 (pet_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE internacao_evento (id INT AUTO_INCREMENT NOT NULL, estabelecimento_id INT NOT NULL, internacao_id INT NOT NULL, pet_id INT NOT NULL, tipo ENUM(\'internacao\',\'alta\',\'ocorrencia\',\'peso\',\'prescricao\',\'medicacao_exec\'), titulo VARCHAR(255) NOT NULL, descricao LONGTEXT DEFAULT NULL, data_hora DATETIME NOT NULL, criado_em DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE veterinario (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, telefone VARCHAR(20) NOT NULL, especialidade VARCHAR(255) NOT NULL, estabelecimento_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE box ADD CONSTRAINT FK_8A9483A966F7FB6 FOREIGN KEY (pet_id) REFERENCES pet (id)');
        $this->addSql('ALTER TABLE financeiropendente CHANGE metodo_pagamento metodo_pagamento ENUM(\'dinheiro\', \'pix\', \'credito\', \'debito\', \'pendente\')');
        $this->addSql('ALTER TABLE internacao ADD situacao VARCHAR(255) DEFAULT NULL, ADD risco VARCHAR(255) DEFAULT NULL, ADD box VARCHAR(255) DEFAULT NULL, ADD alta_prevista DATE DEFAULT NULL, ADD diagnostico LONGTEXT DEFAULT NULL, ADD prognostico LONGTEXT DEFAULT NULL, ADD anotacoes LONGTEXT DEFAULT NULL, ADD veterinario_id INT DEFAULT NULL, CHANGE data_inicio data_inicio DATETIME NOT NULL, CHANGE motivo motivo LONGTEXT DEFAULT NULL, CHANGE dono_id dono_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pet DROP tipo, DROP data_cadastro, CHANGE dono_id dono_id INT DEFAULT NULL, CHANGE observacoes observacoes LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE planos CHANGE titulo titulo VARCHAR(255) NOT NULL, CHANGE descricao descricao LONGTEXT NOT NULL, CHANGE valor valor VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE trial trial INT NOT NULL, CHANGE dataTrial dataTrial DATETIME NOT NULL, CHANGE dataPlano dataPlano DATETIME NOT NULL');
        $this->addSql('ALTER TABLE servico ADD estabelecimento_id INT NOT NULL, ADD tipo VARCHAR(20) DEFAULT \'clinica\' NOT NULL');
        $this->addSql('DROP INDEX nome_usuario ON usuario');
        $this->addSql('ALTER TABLE usuario DROP data_trial, CHANGE nome_usuario nome_usuario VARCHAR(255) DEFAULT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE roles roles JSON NOT NULL, CHANGE access_level access_level VARCHAR(255) NOT NULL, CHANGE petshop_id petshop_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE box DROP FOREIGN KEY FK_8A9483A966F7FB6');
        $this->addSql('DROP TABLE box');
        $this->addSql('DROP TABLE internacao_evento');
        $this->addSql('DROP TABLE veterinario');
        $this->addSql('ALTER TABLE financeiropendente CHANGE metodo_pagamento metodo_pagamento VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE internacao DROP situacao, DROP risco, DROP box, DROP alta_prevista, DROP diagnostico, DROP prognostico, DROP anotacoes, DROP veterinario_id, CHANGE data_inicio data_inicio DATE NOT NULL, CHANGE motivo motivo VARCHAR(255) DEFAULT NULL, CHANGE dono_id dono_id INT NOT NULL');
        $this->addSql('ALTER TABLE pet ADD tipo VARCHAR(255) DEFAULT NULL, ADD data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE observacoes observacoes VARCHAR(255) DEFAULT NULL, CHANGE dono_id dono_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE planos CHANGE titulo titulo VARCHAR(255) DEFAULT NULL, CHANGE descricao descricao TEXT DEFAULT NULL, CHANGE valor valor NUMERIC(10, 2) DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT \'Inativo\', CHANGE trial trial INT DEFAULT NULL, CHANGE dataTrial dataTrial DATETIME DEFAULT NULL, CHANGE dataPlano dataPlano DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE servico DROP estabelecimento_id, DROP tipo');
        $this->addSql('ALTER TABLE usuario ADD data_trial DATETIME DEFAULT NULL, CHANGE nome_usuario nome_usuario VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(45) NOT NULL, CHANGE roles roles VARCHAR(45) DEFAULT \'["ROLE_ADMIN"]\' NOT NULL, CHANGE access_level access_level VARCHAR(255) DEFAULT NULL, CHANGE petshop_id petshop_id BIGINT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX nome_usuario ON usuario (nome_usuario)');
    }
}
