<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250801034039 extends AbstractMigration
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
        $this->addSql('CREATE TABLE consulta (id INT AUTO_INCREMENT NOT NULL, estabelecimento_id INT NOT NULL, cliente_id INT NOT NULL, pet_id INT NOT NULL, data DATE NOT NULL, hora TIME NOT NULL, observacoes LONGTEXT DEFAULT NULL, status VARCHAR(20) DEFAULT \'aguardando\' NOT NULL, criado_em DATETIME NOT NULL, anamnese LONGTEXT DEFAULT NULL, tipo VARCHAR(100) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE financeiro (id INT AUTO_INCREMENT NOT NULL, descricao VARCHAR(255) DEFAULT NULL, valor NUMERIC(10, 0) DEFAULT NULL, data DATETIME DEFAULT NULL, pet_id INT DEFAULT NULL, pet_nome VARCHAR(255) DEFAULT NULL, origem VARCHAR(255) DEFAULT NULL, status VARCHAR(255) DEFAULT NULL, estabelecimento_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE financeiropendente (id INT AUTO_INCREMENT NOT NULL, descricao VARCHAR(255) NOT NULL, valor NUMERIC(10, 2) NOT NULL, data DATETIME NOT NULL, pet_id INT DEFAULT NULL, metodo_pagamento ENUM(\'dinheiro\', \'pix\', \'credito\', \'debito\', \'pendente\'), agendamento_id INT DEFAULT NULL, estabelecimento_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE hospedagem_caes (id INT AUTO_INCREMENT NOT NULL, cliente_id INT NOT NULL, pet_id INT NOT NULL, data_entrada DATETIME NOT NULL, data_saida DATETIME NOT NULL, valor VARCHAR(255) NOT NULL, observacoes LONGTEXT NOT NULL, estabelecimento_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE internacao (id INT AUTO_INCREMENT NOT NULL, data_inicio DATE NOT NULL, motivo VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, pet_id INT NOT NULL, dono_id INT NOT NULL, estabelecimento_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE receita (id INT AUTO_INCREMENT NOT NULL, estabelecimento_id INT NOT NULL, pet_id INT NOT NULL, data DATE NOT NULL, resumo VARCHAR(255) DEFAULT NULL, cabecalho LONGTEXT DEFAULT NULL, conteudo LONGTEXT DEFAULT NULL, rodape LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE servico (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(255) DEFAULT NULL, descricao VARCHAR(255) DEFAULT NULL, valor NUMERIC(10, 0) DEFAULT NULL, estabelecimento_id INT NOT NULL, tipo VARCHAR(20) DEFAULT \'clinica\' NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('DROP TABLE colaborador');
        $this->addSql('ALTER TABLE estabelecimento CHANGE razaoSocial razaoSocial VARCHAR(255) NOT NULL, CHANGE cnpj cnpj VARCHAR(20) NOT NULL, CHANGE rua rua VARCHAR(255) NOT NULL, CHANGE numero numero VARCHAR(60) NOT NULL, CHANGE complemento complemento VARCHAR(255) DEFAULT NULL, CHANGE bairro bairro VARCHAR(255) NOT NULL, CHANGE cidade cidade VARCHAR(255) NOT NULL, CHANGE pais pais VARCHAR(255) DEFAULT NULL, CHANGE cep cep INT NOT NULL, CHANGE status status VARCHAR(25) NOT NULL, CHANGE planoId planoId INT NOT NULL, CHANGE dataPlanoInicio dataPlanoInicio DATETIME NOT NULL, CHANGE dataPlanoFim dataPlanoFim DATETIME NOT NULL');
        $this->addSql('ALTER TABLE menu_grupo_modulo CHANGE id_grupÃo id_grupo INT NOT NULL');
        $this->addSql('ALTER TABLE planos CHANGE titulo titulo VARCHAR(255) NOT NULL, CHANGE descricao descricao LONGTEXT NOT NULL, CHANGE valor valor VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE trial trial INT NOT NULL, CHANGE dataTrial dataTrial DATETIME NOT NULL, CHANGE dataPlano dataPlano DATETIME NOT NULL');
        $this->addSql('DROP INDEX nome_usuario ON usuario');
        $this->addSql('ALTER TABLE usuario CHANGE nome_usuario nome_usuario VARCHAR(255) DEFAULT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE roles roles JSON NOT NULL, CHANGE access_level access_level VARCHAR(255) NOT NULL, CHANGE petshop_id petshop_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE colaborador (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, cargo VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, telefone VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, email VARCHAR(150) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, data_admissao DATE DEFAULT NULL, ativo TINYINT(1) DEFAULT 1, criado_em DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP TABLE Cliente');
        $this->addSql('DROP TABLE Pet');
        $this->addSql('DROP TABLE agendamento');
        $this->addSql('DROP TABLE agendamento_pet_servico');
        $this->addSql('DROP TABLE consulta');
        $this->addSql('DROP TABLE financeiro');
        $this->addSql('DROP TABLE financeiropendente');
        $this->addSql('DROP TABLE hospedagem_caes');
        $this->addSql('DROP TABLE internacao');
        $this->addSql('DROP TABLE receita');
        $this->addSql('DROP TABLE servico');
        $this->addSql('ALTER TABLE usuario CHANGE nome_usuario nome_usuario VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(45) NOT NULL, CHANGE roles roles VARCHAR(45) DEFAULT \'["ROLE_ADMIN"]\' NOT NULL, CHANGE access_level access_level VARCHAR(255) DEFAULT NULL, CHANGE petshop_id petshop_id BIGINT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX nome_usuario ON usuario (nome_usuario)');
        $this->addSql('ALTER TABLE menu_grupo_modulo CHANGE id_grupo id_grupÃo INT NOT NULL');
        $this->addSql('ALTER TABLE estabelecimento CHANGE razaoSocial razaoSocial VARCHAR(300) DEFAULT NULL, CHANGE cnpj cnpj VARCHAR(25) DEFAULT NULL, CHANGE rua rua VARCHAR(255) DEFAULT NULL, CHANGE numero numero VARCHAR(45) DEFAULT NULL, CHANGE complemento complemento VARCHAR(200) DEFAULT NULL, CHANGE bairro bairro VARCHAR(255) DEFAULT NULL, CHANGE cidade cidade VARCHAR(255) DEFAULT NULL, CHANGE pais pais VARCHAR(200) DEFAULT NULL, CHANGE cep cep INT DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT \'Inativo\' NOT NULL, CHANGE planoId planoId INT DEFAULT NULL, CHANGE dataPlanoInicio dataPlanoInicio DATETIME DEFAULT NULL, CHANGE dataPlanoFim dataPlanoFim DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE planos CHANGE titulo titulo VARCHAR(255) DEFAULT NULL, CHANGE descricao descricao TEXT DEFAULT NULL, CHANGE valor valor NUMERIC(10, 2) DEFAULT NULL, CHANGE status status VARCHAR(255) DEFAULT \'Inativo\', CHANGE trial trial INT DEFAULT NULL, CHANGE dataTrial dataTrial DATETIME DEFAULT NULL, CHANGE dataPlano dataPlano DATETIME DEFAULT NULL');
    }
}
