-- Migration: Adiciona coluna status na tabela veterinario
-- Dados nunca são excluídos; status controla atividade do profissional.
-- Executar em todos os bancos de tenant (homepet_*).

ALTER TABLE `veterinario`
    ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'ativo'
        COMMENT 'ativo | inativo — Registro jamais excluído para fins de auditoria'
        AFTER `crmv`;

-- Garante que registros existentes fiquem como ativos
UPDATE `veterinario` SET `status` = 'ativo' WHERE `status` IS NULL OR `status` = '';
