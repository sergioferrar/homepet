-- =====================================================
-- Script para adicionar/corrigir colunas na tabela venda
-- Data: 2026-02-04
-- Descrição: Adiciona colunas origem e status se não existirem
-- =====================================================

-- Adiciona coluna origem se não existir
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'venda' 
    AND COLUMN_NAME = 'origem'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE venda ADD COLUMN origem VARCHAR(50) DEFAULT ''clinica'' AFTER metodo_pagamento',
    'SELECT ''Coluna origem já existe'' AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona coluna status se não existir
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'venda' 
    AND COLUMN_NAME = 'status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE venda ADD COLUMN status ENUM(''Aberta'', ''Pendente'', ''Paga'', ''Inativa'', ''Carrinho'') DEFAULT ''Carrinho'' AFTER origem',
    'ALTER TABLE venda MODIFY COLUMN status ENUM(''Aberta'', ''Pendente'', ''Paga'', ''Inativa'', ''Carrinho'') DEFAULT ''Carrinho'''
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar as alterações
SHOW COLUMNS FROM venda LIKE 'origem';
SHOW COLUMNS FROM venda LIKE 'status';

-- =====================================================
-- Explicação dos Status:
-- =====================================================
-- 'Carrinho'  : Venda criada mas não finalizada (aguardando pagamento no PDV)
-- 'Aberta'    : Venda finalizada e paga
-- 'Pendente'  : Venda finalizada mas pagamento pendente (fiado)
-- 'Paga'      : Venda que estava pendente e foi confirmada
-- 'Inativa'   : Venda cancelada/inativada
-- =====================================================
