-- Adiciona coluna CPF na tabela cliente
ALTER TABLE cliente ADD COLUMN cpf VARCHAR(14) NULL AFTER telefone;
