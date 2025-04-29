#CREATE TABLE `agendamento_pet_servico` (
#  `agendamentoId` INT NOT NULL,
#  `petId` INT NOT NULL,
#  `servicoId` INT NOT NULL,
#  `id` INT NOT NULL AUTO_INCREMENT,
#  PRIMARY KEY (`id`));
  
#ALTER TABLE `agendamento` 
#DROP COLUMN `servico_id`,
#DROP COLUMN `pet_id`;

#ALTER TABLE `agendamento` 
#ADD COLUMN `donoId` INT NULL AFTER `taxa_taxi_dog`;


CREATE TABLE hospedagem_caes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    pet_id INT,
    data_entrada DATE,
    data_saida DATE,
    valor DECIMAL(10,2),
    observacoes TEXT,
    FOREIGN KEY (cliente_id) REFERENCES cliente(id),
    FOREIGN KEY (pet_id) REFERENCES pet(id)
);


## NA BASE DE LOGIN CRIAR ESTA TABELA
CREATE TABLE `planos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(255) NULL,
  `descricao` TEXT NULL,
  `valor` DECIMAL(10,2) NULL,
  `status` ENUM('Ativo', 'Inativo') NULL DEFAULT 'Inativo',
  `trial` INT NULL,
  `dataTrial` TIMESTAMP NULL,
  `dataPlano` DATETIME NULL,
  PRIMARY KEY (`id`));


ALTER TABLE `estabelecimento` 
ADD COLUMN `planoId` INT NULL DEFAULT NULL AFTER `dataAtualizacao`,
ADD COLUMN `dataPlanoInicio` DATETIME NULL AFTER `planoId`,
ADD COLUMN `dataPlanoFim` DATETIME NULL AFTER `dataPlanoInicio`;



CREATE TABLE estabelecimento (
  id int NOT NULL AUTO_INCREMENT,
  razaoSocial varchar(300) DEFAULT NULL,
  cnpj varchar(25) DEFAULT NULL,
  rua varchar(255) DEFAULT NULL,
  numero varchar(45) DEFAULT NULL,
  complemento varchar(200) DEFAULT NULL,
  bairro varchar(255) DEFAULT NULL,
  cidade varchar(255) DEFAULT NULL,
  pais varchar(200) DEFAULT NULL,
  cep int DEFAULT NULL,
  status enum('Ativo','Suspenso','Inativo') NOT NULL DEFAULT 'Inativo',
  dataCadastro timestamp NULL DEFAULT NULL,
  dataAtualizacao timestamp NULL DEFAULT NULL,
  planoId int DEFAULT NULL,
  dataPlanoInicio datetime DEFAULT NULL,
  dataPlanoFim datetime DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE planos (
  id int NOT NULL AUTO_INCREMENT,
  titulo varchar(255) DEFAULT NULL,
  descricao text,
  valor decimal(10,2) DEFAULT NULL,
  status enum('Ativo','Inativo') DEFAULT 'Inativo',
  trial int DEFAULT NULL,
  dataTrial timestamp NULL DEFAULT NULL,
  dataPlano datetime DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE usuario (
  id int NOT NULL AUTO_INCREMENT,
  nome_usuario varchar(255) NOT NULL,
  senha varchar(255) NOT NULL,
  email varchar(45) NOT NULL,
  roles varchar(45) NOT NULL DEFAULT '["ROLE_ADMIN"]',
  access_level enum('Super Admin','Admin','Atendente','Balconista') DEFAULT NULL,
  petshop_id bigint NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY nome_usuario (nome_usuario)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;