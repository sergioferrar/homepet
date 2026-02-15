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


CREATE TABLE `estoque` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `produtoId` INT NULL,
  `estabelecimento_id` INT NULL,
  `local_estoque_id` VARCHAR(45) NULL,
  `quantidade_atual` INT NULL,
  `quantidade_reserva` INT NULL,
  `quantidade_disponivel` INT NULL,
  `estoque_minimo` INT NULL,
  `etoque_maximo` INT NULL,
  `custo_medio` DOUBLE NULL,
  `custo_ultima_compra` DOUBLE NULL,
  `refrigerado` INT NULL,
  `controla_lote` INT NULL,
  `controla_validade` INT NULL,
  `status` ENUM('ativo', 'inativo', 'suspenso') NULL,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `updated_by` DATETIME NULL,
  PRIMARY KEY (`id`));


ALTER TABLE venda 
MODIFY COLUMN status ENUM('Aberta', 'Pendente', 'Paga', 'Inativa', 'Carrinho') 
DEFAULT 'Carrinho';

INSERT INTO estoque (
    produtoId,
    estabelecimento_id,
    quantidade_atual,
    quantidade_reserva,
    quantidade_disponivel,
    estoque_minimo,
    custo_medio,
    custo_ultima_compra,
    refrigerado,
    controla_lote,
    controla_validade,
    status,
    created_at,
    updated_at
)
SELECT 
    p.id AS produtoId,
    p.estabelecimento_id,
    COALESCE(p.estoque_atual, 0) AS quantidade_atual,
    0 AS quantidade_reserva,
    COALESCE(p.estoque_atual, 0) AS quantidade_disponivel,
    0 AS estoque_minimo,
    COALESCE(p.preco_custo, 0) AS custo_medio,
    COALESCE(p.preco_custo, 0) AS custo_ultima_compra,
    CASE WHEN p.refrigerado = 'Sim' THEN 1 ELSE 0 END AS refrigerado,
    0 AS controla_lote,
    CASE WHEN p.data_validade IS NOT NULL THEN 1 ELSE 0 END AS controla_validade,
    'ativo' AS status,
    NOW() AS created_at,
    NOW() AS updated_at
FROM produto p
WHERE NOT EXISTS (
    -- Evita duplicação se já existir registro
    SELECT 1 FROM estoque e 
    WHERE e.produtoId = p.id 
    AND e.estabelecimento_id = p.estabelecimento_id
);