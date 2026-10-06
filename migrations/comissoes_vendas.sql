-- =====================================================
-- Comissões em vendas e itens — rodar em cada tenant (homepet_XX).
-- Usa procedure para só criar coluna/índice quando ainda não existem.
-- =====================================================

DELIMITER //
DROP PROCEDURE IF EXISTS add_column_if_missing //
CREATE PROCEDURE add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = p_table
          AND column_name  = p_column
    ) AND EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name   = p_table
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE ', p_table, ' ADD COLUMN ', p_column, ' ', p_definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //

DROP PROCEDURE IF EXISTS add_index_if_missing //
CREATE PROCEDURE add_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_columns VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name   = p_table
          AND index_name   = p_index
    ) AND EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name   = p_table
    ) THEN
        SET @ddl = CONCAT('CREATE INDEX ', p_index, ' ON ', p_table, ' (', p_columns, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- Veterinário que fez a venda e % de comissão específico da venda
CALL add_column_if_missing('venda', 'veterinario_id',       'INT NULL');
CALL add_column_if_missing('venda', 'comissao_percentual',  'DECIMAL(5,2) NULL');
CALL add_index_if_missing ('venda', 'idx_venda_vet',        'veterinario_id');

-- % de comissão aplicado no item na hora da venda (snapshot)
CALL add_column_if_missing('venda_item', 'comissao_percentual', 'DECIMAL(5,2) NULL');

-- % padrão de comissão por serviço e por produto
CALL add_column_if_missing('servico', 'comissao_percentual', 'DECIMAL(5,2) NULL');
CALL add_column_if_missing('produto', 'comissao_percentual', 'DECIMAL(5,2) NULL');

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;
