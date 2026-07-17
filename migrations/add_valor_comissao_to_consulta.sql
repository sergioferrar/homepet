-- Migration: Adiciona coluna valor na tabela consulta
-- Guarda o valor do atendimento ajustado manualmente no relatório de comissões.
-- NULL = usa o valor do serviço da clínica cadastrado com o mesmo nome do tipo.
-- Executar em todos os bancos de tenant (homepet_*).

ALTER TABLE `consulta`
    ADD COLUMN IF NOT EXISTS `valor` DECIMAL(10,2) NULL
        COMMENT 'Valor do atendimento ajustado no relatório de comissões (NULL = valor do serviço de mesmo nome)'
        AFTER `tipo`;
