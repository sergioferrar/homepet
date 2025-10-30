-- =====================================================
-- Script para adicionar coluna pet_id na tabela venda
-- =====================================================

-- Adicionar coluna pet_id na tabela venda
ALTER TABLE venda 
ADD COLUMN pet_id INT DEFAULT NULL 
AFTER observacao;

-- Adicionar índice para melhorar performance nas consultas
CREATE INDEX idx_venda_pet_id ON venda(pet_id);

-- Verificar se foi criado corretamente
DESCRIBE venda;

-- =====================================================
-- Fim do script
-- =====================================================
