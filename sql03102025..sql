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