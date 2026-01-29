-- Adiciona coluna origem na tabela financeiropendente
ALTER TABLE financeiropendente 
ADD COLUMN origem VARCHAR(50) DEFAULT 'clinica' AFTER metodo_pagamento;
