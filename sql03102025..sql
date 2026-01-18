ALTER TABLE internacao_evento 
MODIFY COLUMN tipo ENUM(
  'internacao',
  'alta',
  'ocorrencia',
  'peso',
  'prescricao',
  'medicacao_exec',
  'obito',
  'cancelar'
);



CREATE TABLE vacina (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    pet_id INT NOT NULL,
    tipo VARCHAR(100) NOT NULL,
    data_aplicacao DATE NOT NULL,
    data_validade DATE NOT NULL,
    lote VARCHAR(100) DEFAULT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vacina_pet FOREIGN KEY (pet_id) REFERENCES pet(id),
    INDEX idx_estabelecimento (estabelecimento_id),
    INDEX idx_pet (pet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `produto` 
ADD COLUMN `codigo` BIGINT NULL AFTER `nome`,
ADD COLUMN `refrigerado` ENUM('Sim', 'Não') NULL DEFAULT 'Não' AFTER `codigo`,
ADD COLUMN `data_validade` DATETIME NULL AFTER `data_cadastro`,
ADD COLUMN `codigo_fabrica` VARCHAR(255) NULL AFTER `data_validade`;

ALTER TABLE `produto` 
ADD COLUMN `ncm` INT NOT NULL AFTER `codigo_fabrica`,
ADD COLUMN `cfop` INT NOT NULL AFTER `ncm`,
ADD COLUMN `cest` DOUBLE NULL DEFAULT NULL AFTER `cfop`,
ADD COLUMN `aliquota_icms` DOUBLE NULL DEFAULT NULL AFTER `cest`,
ADD COLUMN `aliquota_pis` DOUBLE NULL DEFAULT NULL AFTER `aliquota_icms`,
ADD COLUMN `aliquota_cofins` DOUBLE NULL DEFAULT NULL AFTER `aliquota_pis`,
ADD COLUMN `aliquota_ipi` DOUBLE NULL DEFAULT NULL AFTER `aliquota_cofins`,
ADD COLUMN `aliquota_iss` DOUBLE NULL DEFAULT NULL AFTER `aliquota_ipi`,
ADD COLUMN `aliquota_ibs` DOUBLE NULL DEFAULT NULL AFTER `aliquota_iss`,
ADD COLUMN `aliquota_cbs` DOUBLE NULL DEFAULT NULL AFTER `aliquota_ibs`;

CREATE TABLE `homepet_login`.`config` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` INT NOT NULL,
  `chave` VARCHAR(255) NULL,
  `valor` TEXT NULL,
  `tipo` VARCHAR(255) NULL,
  `observacao` TEXT NULL,
  PRIMARY KEY (`id`),
);



ALTER TABLE `venda` 
ADD COLUMN `origem` VARCHAR(255) NULL AFTER `pet_id`,
ADD COLUMN `status` ENUM('Aberta', 'Pendente', 'Paga') NULL DEFAULT 'Aberta' AFTER `origem`;


ALTER TABLE `venda_item` 
ADD COLUMN `tipo` ENUM('servico', 'produto', 'medicamento') NULL DEFAULT 'produto' AFTER `subtotal`;

ALTER TABLE `financeiro` 
ADD COLUMN `venda` INT NULL DEFAULT NULL AFTER `parcelas`;

