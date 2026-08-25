-- Script para adicionar o campo 'como_conheceu' na tabela cliente
-- Execute este script no banco de dados de cada estabelecimento (homepet_XXX)

-- Verificar se a coluna já existe antes de adicionar
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'cliente' 
  AND COLUMN_NAME = 'como_conheceu' 
  AND TABLE_SCHEMA = DATABASE();

-- Se a coluna não existir, adicionar ela
ALTER TABLE cliente 
ADD COLUMN IF NOT EXISTS como_conheceu VARCHAR(255) NULL 
COMMENT 'Canal por onde o cliente conheceu a empresa';

-- Opcional: atualizar registros existentes com um valor padrão
-- UPDATE cliente SET como_conheceu = 'Não informado' WHERE como_conheceu IS NULL;