-- Rodar em CADA tenant (homepet_XX):
--   USE homepet_26;
--   SOURCE migrations/comissao_padrao_veterinario_tenant.sql;
--
-- Se já tiver a coluna, o MySQL retorna "Duplicate column name" — pode ignorar.

ALTER TABLE veterinario
    ADD COLUMN comissao_padrao DECIMAL(5,2) NULL;
