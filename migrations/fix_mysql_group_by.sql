-- Fix MySQL ONLY_FULL_GROUP_BY mode error
-- Este erro ocorre quando o MySQL está em modo strict e queries usam GROUP BY sem incluir todas as colunas do SELECT

-- Opção 1: Desabilitar ONLY_FULL_GROUP_BY temporariamente (para desenvolvimento)
SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));
SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));

-- Opção 2: Verificar o modo atual
SELECT @@sql_mode;

-- Opção 3: Configurar permanentemente no arquivo my.cnf ou my.ini
-- Adicione esta linha na seção [mysqld]:
-- sql_mode=STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION

-- Para aplicar permanentemente, edite o arquivo de configuração do MySQL:
-- Linux: /etc/mysql/my.cnf ou /etc/my.cnf
-- Windows: C:\ProgramData\MySQL\MySQL Server X.X\my.ini
-- 
-- Adicione ou modifique:
-- [mysqld]
-- sql_mode=STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
--
-- Depois reinicie o MySQL:
-- Linux: sudo service mysql restart
-- Windows: Reinicie o serviço MySQL no Services
