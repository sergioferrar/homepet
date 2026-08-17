-- Migration: Adiciona status de óbito na tabela pet
-- Dados nunca são excluídos; status controla se o pet pode ser selecionado
-- em novos lançamentos (agendamento, venda, atendimento), mas ele continua
-- aparecendo normalmente em listagens, fichas e relatórios.
-- Executar em todos os bancos de tenant (homepet_*).

ALTER TABLE `pet`
    ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'ativo'
        COMMENT 'ativo | obito — Registro jamais excluído, apenas desabilitado para seleção'
        AFTER `castrado`,
    ADD COLUMN IF NOT EXISTS `data_obito` DATETIME NULL
        COMMENT 'Data/hora em que o óbito foi registrado na ficha/internação'
        AFTER `status`;

-- Garante que registros existentes fiquem como ativos
UPDATE `pet` SET `status` = 'ativo' WHERE `status` IS NULL OR `status` = '';
