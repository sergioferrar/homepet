<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250608023500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE Cliente (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, telefone VARCHAR(255) DEFAULT NULL, rua VARCHAR(255) DEFAULT NULL, numero INT DEFAULT NULL, complemento VARCHAR(255) DEFAULT NULL, bairro VARCHAR(255) DEFAULT NULL, cidade VARCHAR(255) DEFAULT NULL, whatsapp VARCHAR(6) NOT NULL, cep INT NOT NULL, estabelecimento_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Pet (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(255) DEFAULT NULL, idade VARCHAR(255) DEFAULT NULL, sexo VARCHAR(255) DEFAULT NULL, raca VARCHAR(255) DEFAULT NULL, porte VARCHAR(255) DEFAULT NULL, observacoes LONGTEXT DEFAULT NULL, dono_id INT DEFAULT NULL, especie VARCHAR(255) DEFAULT NULL, estabelecimento_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE agendamento (id INT AUTO_INCREMENT NOT NULL, data DATETIME DEFAULT NULL, concluido TINYINT(1) DEFAULT 0 NOT NULL, pronto TINYINT(1) DEFAULT 0 NOT NULL, horaChegada DATETIME DEFAULT NULL, metodo_pagamento VARCHAR(30) DEFAULT \'pendente\' NOT NULL, horaSaida DATETIME DEFAULT NULL, taxi_dog TINYINT(1) DEFAULT 0 NOT NULL, taxa_taxi_dog NUMERIC(10, 2) DEFAULT NULL, pacote_semanal TINYINT(1) DEFAULT 0 NOT NULL, pacote_quinzenal TINYINT(1) DEFAULT 0 NOT NULL, donoId INT DEFAULT NULL, status VARCHAR(20) DEFAULT \'aguardando\' NOT NULL, estabelecimento_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE agendamento_pet_servico (id INT AUTO_INCREMENT NOT NULL, agendamentoId INT NOT NULL, petId INT NOT NULL, servicoId INT NOT NULL, estabelecimento_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE consulta (id INT AUTO_INCREMENT NOT NULL, estabelecimento_id INT NOT NULL, cliente_id INT NOT NULL, pet_id INT NOT NULL, data DATE NOT NULL, hora TIME NOT NULL, observacoes LONGTEXT DEFAULT NULL, criado_em DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE estabelecimento (id INT AUTO_INCREMENT NOT NULL, razaoSocial VARCHAR(255) NOT NULL, cnpj VARCHAR(20) NOT NULL, rua VARCHAR(255) NOT NULL, numero VARCHAR(60) NOT NULL, complemento VARCHAR(255) DEFAULT NULL, bairro VARCHAR(255) NOT NULL, cidade VARCHAR(255) NOT NULL, pais VARCHAR(255) DEFAULT NULL, cep INT NOT NULL, status VARCHAR(25) NOT NULL, dataCadastro DATETIME DEFAULT NULL, dataAtualizacao DATETIME DEFAULT NULL, planoId INT NOT NULL, dataPlanoInicio DATETIME NOT NULL, dataPlanoFim DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE financeiro (id INT AUTO_INCREMENT NOT NULL, descricao VARCHAR(255) DEFAULT NULL, valor NUMERIC(10, 0) DEFAULT NULL, data DATETIME DEFAULT NULL, pet_id INT DEFAULT NULL, pet_nome VARCHAR(255) DEFAULT NULL, estabelecimento_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE financeiropendente (id INT AUTO_INCREMENT NOT NULL, descricao VARCHAR(255) NOT NULL, valor NUMERIC(10, 2) NOT NULL, data DATETIME NOT NULL, pet_id INT DEFAULT NULL, metodo_pagamento ENUM(\'dinheiro\', \'pix\', \'credito\', \'debito\', \'pendente\'), agendamento_id INT DEFAULT NULL, estabelecimento_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE grupo (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(255) NOT NULL, parent INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE hospedagem_caes (id INT AUTO_INCREMENT NOT NULL, cliente_id INT NOT NULL, pet_id INT NOT NULL, data_entrada DATETIME NOT NULL, data_saida DATETIME NOT NULL, valor VARCHAR(255) NOT NULL, observacoes LONGTEXT NOT NULL, estabelecimento_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu (id INT AUTO_INCREMENT NOT NULL, titulo VARCHAR(255) NOT NULL, parent INT DEFAULT NULL, descricao LONGTEXT DEFAULT NULL, rota VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, icone VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu_grupo_modulo (id INT AUTO_INCREMENT NOT NULL, id_menu INT NOT NULL, id_grupÃo INT NOT NULL, id_modulo INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE modulo (id INT AUTO_INCREMENT NOT NULL, titulo VARCHAR(255) NOT NULL, trial INT DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, descricao LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE servico (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(255) DEFAULT NULL, descricao VARCHAR(255) DEFAULT NULL, valor NUMERIC(10, 0) DEFAULT NULL, estabelecimento_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE planos CHANGE titulo titulo VARCHAR(255) NOT NULL, CHANGE descricao descricao LONGTEXT NOT NULL, CHANGE valor valor VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE trial trial INT NOT NULL, CHANGE dataTrial dataTrial DATETIME NOT NULL, CHANGE dataPlano dataPlano DATETIME NOT NULL');
        $this->addSql('DROP INDEX nome_usuario ON usuario');
        $this->addSql('ALTER TABLE usuario CHANGE nome_usuario nome_usuario VARCHAR(255) DEFAULT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE roles roles JSON NOT NULL, CHANGE access_level access_level VARCHAR(255) NOT NULL, CHANGE petshop_id petshop_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE Cliente');
        $this->addSql('DROP TABLE Pet');
        $this->addSql('DROP TABLE agendamento');
        $this->addSql('DROP TABLE agendamento_pet_servico');
        $this->addSql('DROP TABLE consulta');
        $this->addSql('DROP TABLE estabelecimento');
        $this->addSql('DROP TABLE financeiro');
        $this->addSql('DROP TABLE financeiropendente');
        $this->addSql('DROP TABLE grupo');
        $this->addSql('DROP TABLE hospedagem_caes');
        $this->addSql('DROP TABLE menu');
        $this->addSql('DROP TABLE menu_grupo_modulo');
        $this->addSql('DROP TABLE modulo');
        $this->addSql('DROP TABLE servico');
        $this->addSql('ALTER TABLE planos CHANGE titulo titulo VARCHAR(255) DEFAULT NULL, CHANGE descricao descricao TEXT DEFAULT NULL, CHANGE valor valor NUMERIC(10, 2) DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT \'Inativo\', CHANGE trial trial INT DEFAULT NULL, CHANGE dataTrial dataTrial DATETIME DEFAULT NULL, CHANGE dataPlano dataPlano DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE usuario CHANGE nome_usuario nome_usuario VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(45) NOT NULL, CHANGE roles roles VARCHAR(45) DEFAULT \'["ROLE_ADMIN"]\' NOT NULL, CHANGE access_level access_level VARCHAR(255) DEFAULT NULL, CHANGE petshop_id petshop_id BIGINT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX nome_usuario ON usuario (nome_usuario)');
    }
}
