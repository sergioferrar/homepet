-- =====================================================
-- Migration: vincula cada venda a um atendimento (consulta)
-- Data: 2026-07-23
-- Descrição: adiciona venda.consulta_id + índice, permitindo
--            agrupar/navegar as vendas por consulta na ficha do pet.
--
-- Execute uma vez em CADA base de tenant (homepet_<id>).
-- Ex.: mysql -u root -p homepet_12 < migrations/add_consulta_id_to_venda.sql
-- =====================================================

-- ── 1. Coluna consulta_id ────────────────────────────────────────────────
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'venda'
      AND COLUMN_NAME  = 'consulta_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE venda ADD COLUMN consulta_id INT NULL AFTER pet_id',
    'SELECT ''Coluna consulta_id já existe'' AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── 2. Índice ────────────────────────────────────────────────────────────
SET @idx_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'venda'
      AND INDEX_NAME   = 'idx_venda_consulta_id'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE venda ADD INDEX idx_venda_consulta_id (consulta_id)',
    'SELECT ''Índice idx_venda_consulta_id já existe'' AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ── 3. Backfill (opcional, mas recomendado) ──────────────────────────────
-- Amarra as vendas antigas ao atendimento do mesmo pet no mesmo dia
-- (o mais recente do dia). Vendas sem atendimento ficam com NULL e
-- aparecem no bloco "Sem atendimento vinculado" da ficha.
UPDATE venda v
SET v.consulta_id = (
    SELECT c.id
    FROM consulta c
    WHERE c.estabelecimento_id = v.estabelecimento_id
      AND c.pet_id             = v.pet_id
      AND c.data               = DATE(v.data)
      AND c.status <> 'cancelado'
    ORDER BY c.hora DESC, c.id DESC
    LIMIT 1
)
WHERE v.consulta_id IS NULL
  AND v.pet_id IS NOT NULL;

-- ── 4. Status 'Cancelada' no ENUM ────────────────────────────────────────
-- VendaRepository::inativar() grava 'Cancelada' e vários SELECTs filtram
-- por esse valor, mas ele não existia no ENUM — em sql_mode STRICT isso
-- estourava "Data truncated for column 'status'" (um dos erros silenciosos).
ALTER TABLE venda
    MODIFY COLUMN status ENUM('Aberta','Pendente','Paga','Inativa','Carrinho','Cancelada')
    DEFAULT 'Carrinho';

SELECT 'Migration concluída: venda.consulta_id + status Cancelada' AS status;
