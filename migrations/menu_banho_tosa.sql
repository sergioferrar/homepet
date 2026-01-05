-- =====================================================
-- Script para criar menu "Banho e Tosa" e reorganizar menus
-- Executar no banco homepet_login
-- Data: 05/01/2026
-- =====================================================

-- =====================================================
-- PARTE 1: Criar o menu pai "Banho e Tosa"
-- =====================================================
INSERT INTO homepet_login.menu (titulo, parent, descricao, rota, status, icone, ordem, modulo) 
VALUES ('Banho e Tosa', NULL, 'Serviços de Banho e Tosa', '#', 'ativo', 'droplet', 2, 7);

-- Pegar o ID do menu recém criado
SET @banho_tosa_id = LAST_INSERT_ID();

-- =====================================================
-- PARTE 2: Mover os itens de agendamento para "Banho e Tosa"
-- =====================================================

-- Mover "Novo Agendamento" (id=2) para Banho e Tosa
UPDATE homepet_login.menu 
SET parent = @banho_tosa_id, ordem = 1 
WHERE id = 2;

-- Mover "Ver todos" (id=23) para Banho e Tosa
UPDATE homepet_login.menu 
SET parent = @banho_tosa_id, ordem = 2 
WHERE id = 23;

-- Mover "Quadro de Banho & Tosa" (id=15) para Banho e Tosa
UPDATE homepet_login.menu 
SET parent = @banho_tosa_id, ordem = 3 
WHERE id = 15;

-- =====================================================
-- PARTE 3: Reorganizar ordem dos menus principais
-- Banho e Tosa deve ficar ACIMA de Clínica Veterinária
-- =====================================================

-- Atendimento = ordem 1
UPDATE homepet_login.menu SET ordem = 1 WHERE id = 34;

-- Banho e Tosa = ordem 2 (já definido no INSERT)

-- Clínica Veterinária = ordem 3
UPDATE homepet_login.menu SET ordem = 3 WHERE id = 35;

-- Gestão = ordem 4
UPDATE homepet_login.menu SET ordem = 4 WHERE id = 36;

-- Financeiro = ordem 5
UPDATE homepet_login.menu SET ordem = 5 WHERE id = 37;

-- Operações = ordem 6
UPDATE homepet_login.menu SET ordem = 6 WHERE id = 38;

-- =====================================================
-- VERIFICAÇÃO: Mostrar resultado final
-- =====================================================
SELECT 'Menus Principais (ordenados):' AS info;
SELECT id, titulo, ordem, modulo, status 
FROM homepet_login.menu 
WHERE parent IS NULL AND status = 'ativo'
ORDER BY ordem;

SELECT 'Submenus de Banho e Tosa:' AS info;
SELECT id, titulo, parent, ordem 
FROM homepet_login.menu 
WHERE parent = @banho_tosa_id
ORDER BY ordem;
