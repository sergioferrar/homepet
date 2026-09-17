-- =====================================================
-- Índices de performance — banco principal (homepet_login)
-- Uso:  USE homepet_login; SOURCE migrations/indices_performance_login.sql;
-- =====================================================

DELIMITER //
DROP PROCEDURE IF EXISTS criar_indice_se_nao_existe //
CREATE PROCEDURE criar_indice_se_nao_existe(
    IN p_tabela VARCHAR(64),
    IN p_indice VARCHAR(64),
    IN p_colunas VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = p_tabela
          AND index_name = p_indice
    ) AND EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = p_tabela
    ) THEN
        SET @ddl = CONCAT('CREATE INDEX ', p_indice, ' ON ', p_tabela, ' (', p_colunas, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

CALL criar_indice_se_nao_existe('usuario',            'idx_usuario_petshop',      'petshop_id');
CALL criar_indice_se_nao_existe('usuario',            'idx_usuario_email',        'email');

CALL criar_indice_se_nao_existe('estabelecimento',    'idx_estab_status',         'status');
CALL criar_indice_se_nao_existe('estabelecimento',    'idx_estab_plano',          'planoId');

CALL criar_indice_se_nao_existe('menu',               'idx_menu_parent',          'parent');
CALL criar_indice_se_nao_existe('menu',               'idx_menu_status',          'status');
CALL criar_indice_se_nao_existe('menu',               'idx_menu_modulo',          'modulo');

CALL criar_indice_se_nao_existe('assinatura_modulo',  'idx_assinatura_estab',     'estabelecimento_id');
CALL criar_indice_se_nao_existe('assinatura_modulo',  'idx_assinatura_status',    'status');

CALL criar_indice_se_nao_existe('invoice',            'idx_invoice_estab_status', 'estabelecimento_id, status');
CALL criar_indice_se_nao_existe('invoice',            'idx_invoice_venc',         'data_vencimento');

DROP PROCEDURE IF EXISTS criar_indice_se_nao_existe;
