-- =====================================================
-- Script para reorganizar ordem dos menus
-- Banho e Tosa acima de Clínica Veterinária
-- Executar no banco homepet_login
-- =====================================================

-- Atendimento = ordem 1
UPDATE menu SET ordem = 1 WHERE id = 34;

-- Banho e Tosa = ordem 2
UPDATE menu SET ordem = 2 WHERE id = 41;

-- Clínica Veterinária = ordem 3
UPDATE menu SET ordem = 3 WHERE id = 35;

-- Gestão = ordem 4
UPDATE menu SET ordem = 4 WHERE id = 36;

-- Financeiro = ordem 5
UPDATE menu SET ordem = 5 WHERE id = 37;

-- Operações = ordem 6
UPDATE menu SET ordem = 6 WHERE id = 38;

-- Verificar resultado
SELECT id, titulo, ordem FROM homepet_login.menu 
WHERE parent IS NULL AND status = 'ativo' 
ORDER BY ordem;
