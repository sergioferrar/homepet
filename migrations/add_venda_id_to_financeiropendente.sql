-- Liga o lançamento de financeiropendente à venda que o originou.
-- Rodar em CADA base de tenant (homepet_<id>) ANTES de subir o código novo.
ALTER TABLE financeiropendente
ADD COLUMN venda_id INT NULL DEFAULT NULL AFTER agendamento_id,
ADD INDEX idx_fp_venda (venda_id);
