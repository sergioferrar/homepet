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
