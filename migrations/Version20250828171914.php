<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250828171914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE internacao_execucao (id INT AUTO_INCREMENT NOT NULL, internacao_id INT NOT NULL, prescricao_id INT NOT NULL, data_execucao DATETIME NOT NULL, anotacoes LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE internacao_prescricao (id INT AUTO_INCREMENT NOT NULL, medicamento_id INT NOT NULL, internacao_id INT NOT NULL, descricao LONGTEXT NOT NULL, data_hora DATETIME NOT NULL, criado_em DATETIME NOT NULL, dose VARCHAR(255) DEFAULT NULL, frequencia VARCHAR(255) DEFAULT NULL, INDEX IDX_31087E54DECC3FDC (medicamento_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE medicamentos (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(150) NOT NULL, via VARCHAR(50) DEFAULT NULL, concentracao VARCHAR(50) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE internacao_prescricao ADD CONSTRAINT FK_31087E54DECC3FDC FOREIGN KEY (medicamento_id) REFERENCES medicamentos (id)');
        $this->addSql('ALTER TABLE financeiropendente CHANGE metodo_pagamento metodo_pagamento ENUM(\'dinheiro\', \'pix\', \'credito\', \'debito\', \'pendente\')');
        $this->addSql('ALTER TABLE internacao_evento CHANGE tipo tipo ENUM(\'internacao\',\'alta\',\'ocorrencia\',\'peso\',\'prescricao\',\'medicacao_exec\')');
        $this->addSql('ALTER TABLE planos CHANGE titulo titulo VARCHAR(255) NOT NULL, CHANGE descricao descricao LONGTEXT NOT NULL, CHANGE valor valor VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE trial trial INT NOT NULL, CHANGE dataTrial dataTrial DATETIME NOT NULL, CHANGE dataPlano dataPlano DATETIME NOT NULL');
        $this->addSql('ALTER TABLE servico ADD estabelecimento_id INT NOT NULL, ADD tipo VARCHAR(20) DEFAULT \'clinica\' NOT NULL');
        $this->addSql('DROP INDEX nome_usuario ON usuario');
        $this->addSql('ALTER TABLE usuario DROP data_trial, CHANGE nome_usuario nome_usuario VARCHAR(255) DEFAULT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE roles roles JSON NOT NULL, CHANGE access_level access_level VARCHAR(255) NOT NULL, CHANGE petshop_id petshop_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE internacao_prescricao DROP FOREIGN KEY FK_31087E54DECC3FDC');
        $this->addSql('DROP TABLE internacao_execucao');
        $this->addSql('DROP TABLE internacao_prescricao');
        $this->addSql('DROP TABLE medicamentos');
        $this->addSql('ALTER TABLE financeiropendente CHANGE metodo_pagamento metodo_pagamento VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE internacao_evento CHANGE tipo tipo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE planos CHANGE titulo titulo VARCHAR(255) DEFAULT NULL, CHANGE descricao descricao TEXT DEFAULT NULL, CHANGE valor valor NUMERIC(10, 2) DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT \'Inativo\', CHANGE trial trial INT DEFAULT NULL, CHANGE dataTrial dataTrial DATETIME DEFAULT NULL, CHANGE dataPlano dataPlano DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE servico DROP estabelecimento_id, DROP tipo');
        $this->addSql('ALTER TABLE usuario ADD data_trial DATETIME DEFAULT NULL, CHANGE nome_usuario nome_usuario VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(45) NOT NULL, CHANGE roles roles VARCHAR(45) DEFAULT \'["ROLE_ADMIN"]\' NOT NULL, CHANGE access_level access_level VARCHAR(255) DEFAULT NULL, CHANGE petshop_id petshop_id BIGINT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX nome_usuario ON usuario (nome_usuario)');
    }
}
