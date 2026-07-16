-- Migration: Adiciona colunas de anexo (encaminhamento) na tabela consulta
-- O arquivo físico é gravado em public/uploads/encaminhamentos com nome randômico
-- de 12 dígitos; o nome original é preservado para o download na timeline.
-- Executar em todos os bancos de tenant (homepet_*).

ALTER TABLE `consulta`
    ADD COLUMN IF NOT EXISTS `attachment` VARCHAR(255) NULL
        COMMENT 'Nome do arquivo no servidor (randômico 12 dígitos + extensão)'
        AFTER `tipo`,
    ADD COLUMN IF NOT EXISTS `attachment_original` VARCHAR(255) NULL
        COMMENT 'Nome original do arquivo enviado pelo usuário (usado no download)'
        AFTER `attachment`;
