-- =====================================================
-- Tabela consulta_pet — pets adicionais de um atendimento
-- Executar no banco DE CADA TENANT (ex.: homepet_26)
-- =====================================================

CREATE TABLE IF NOT EXISTS consulta_pet (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    consulta_id       INT NOT NULL,
    pet_id            INT NOT NULL,
    UNIQUE KEY uk_consulta_pet (consulta_id, pet_id),
    KEY idx_consulta (consulta_id),
    KEY idx_pet (pet_id),
    KEY idx_estab (estabelecimento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chaves estrangeiras (opcionais — só rode se os tipos das colunas baterem)
-- ALTER TABLE consulta_pet
--   ADD CONSTRAINT fk_consulta_pet_consulta FOREIGN KEY (consulta_id) REFERENCES consulta (id) ON DELETE CASCADE,
--   ADD CONSTRAINT fk_consulta_pet_pet      FOREIGN KEY (pet_id)      REFERENCES pet (id)      ON DELETE CASCADE;
