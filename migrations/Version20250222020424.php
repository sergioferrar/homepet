<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250222020424 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE Agendamento (id INT AUTO_INCREMENT NOT NULL, data DATETIME DEFAULT NULL, pet_id INT DEFAULT NULL, servico_id INT DEFAULT NULL, concluido INT DEFAULT NULL, pronto INT DEFAULT NULL, horaChegada DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Cliente (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, telefone VARCHAR(255) DEFAULT NULL, rua VARCHAR(255) DEFAULT NULL, numero INT DEFAULT NULL, complemento VARCHAR(255) DEFAULT NULL, bairro VARCHAR(255) DEFAULT NULL, cidade VARCHAR(255) DEFAULT NULL, whatsapp VARCHAR(6) NOT NULL, cep INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Financeiro (id INT AUTO_INCREMENT NOT NULL, descricao VARCHAR(255) DEFAULT NULL, valor NUMERIC(10, 0) DEFAULT NULL, data DATETIME DEFAULT NULL, pet_id INT DEFAULT NULL, pet_nome VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Pet (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(255) DEFAULT NULL, tipo VARCHAR(255) DEFAULT NULL, idade VARCHAR(255) DEFAULT NULL, dono_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE servico (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(255) DEFAULT NULL, descricao VARCHAR(255) DEFAULT NULL, valor NUMERIC(10, 0) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE usuario (id INT AUTO_INCREMENT NOT NULL, nome_usuario VARCHAR(255) DEFAULT NULL, senha VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE Agendamento');
        $this->addSql('DROP TABLE Cliente');
        $this->addSql('DROP TABLE Financeiro');
        $this->addSql('DROP TABLE Pet');
        $this->addSql('DROP TABLE servico');
        $this->addSql('DROP TABLE usuario');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
