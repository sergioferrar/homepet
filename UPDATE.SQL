CREATE TABLE `agendamento_pet_servico` (
  `agendamentoId` INT NOT NULL,
  `petId` INT NOT NULL,
  `servicoId` INT NOT NULL,
  `id` INT NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`));
  
ALTER TABLE `agendamento` 
DROP COLUMN `servico_id`,
DROP COLUMN `pet_id`;

ALTER TABLE `agendamento` 
ADD COLUMN `donoId` INT NULL AFTER `taxa_taxi_dog`;
