-- Migration: Adicionar campo data_nascimento na tabela pet
-- Data: 2026-01-08
-- Descrição: Substitui o campo idade estático por data de nascimento para cálculo dinâmico da idade

ALTER TABLE pet ADD COLUMN data_nascimento DATE NULL AFTER idade;

-- Índice para consultas por data de nascimento (opcional, mas recomendado)
-- CREATE INDEX idx_pet_data_nascimento ON pet(data_nascimento);
