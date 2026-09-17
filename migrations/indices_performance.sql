-- =====================================================
-- Índices de performance — banco de cada tenant (homepet_XX)
-- Compatível com MySQL 5.7+ e MySQL 8. Ignora índice já criado.
-- Rode em cada database com:  USE homepet_26; SOURCE migrations/indices_performance.sql;
-- =====================================================

DELIMITER //
DROP PROCEDURE IF EXISTS criar_indice_se_nao_existe //
CREATE PROCEDURE criar_indice_se_nao_existe(
    IN p_tabela VARCHAR(64),
    IN p_indice VARCHAR(64),
    IN p_colunas VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = p_tabela
          AND index_name = p_indice
    ) AND EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = p_tabela
    ) THEN
        SET @ddl = CONCAT('CREATE INDEX ', p_indice, ' ON ', p_tabela, ' (', p_colunas, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- ── Vendas / Financeiro ───────────────────────────────
CALL criar_indice_se_nao_existe('venda',              'idx_venda_estab_data',     'estabelecimento_id, data');
CALL criar_indice_se_nao_existe('venda',              'idx_venda_estab_status',   'estabelecimento_id, status');
CALL criar_indice_se_nao_existe('venda',              'idx_venda_pet',            'pet_id');
CALL criar_indice_se_nao_existe('venda',              'idx_venda_origem',         'origem');
CALL criar_indice_se_nao_existe('venda',              'idx_venda_metodo',         'metodo_pagamento');
CALL criar_indice_se_nao_existe('venda_item',         'idx_venda_item_venda',     'venda_id');
CALL criar_indice_se_nao_existe('venda_item',         'idx_venda_item_produto',   'produto_id');
CALL criar_indice_se_nao_existe('venda_pagamento',    'idx_venda_pagto_venda',    'venda_id');

CALL criar_indice_se_nao_existe('financeiropendente', 'idx_fp_estab_status',      'estabelecimento_id, status');
CALL criar_indice_se_nao_existe('financeiropendente', 'idx_fp_estab_data',        'estabelecimento_id, data');
CALL criar_indice_se_nao_existe('financeiropendente', 'idx_fp_pet',               'pet_id');
CALL criar_indice_se_nao_existe('financeiropendente', 'idx_fp_agendamento',       'agendamento_id');

CALL criar_indice_se_nao_existe('financeiro',         'idx_financeiro_estab_data','estabelecimento_id, data');
CALL criar_indice_se_nao_existe('financeiro',         'idx_financeiro_estab_stat','estabelecimento_id, status');
CALL criar_indice_se_nao_existe('financeiro',         'idx_financeiro_pet',       'pet_id');
CALL criar_indice_se_nao_existe('financeiro',         'idx_financeiro_origem',    'origem');

-- ── Cliente / Pet ─────────────────────────────────────
CALL criar_indice_se_nao_existe('cliente',            'idx_cliente_estab',        'estabelecimento_id');
CALL criar_indice_se_nao_existe('cliente',            'idx_cliente_nome',         'nome');
CALL criar_indice_se_nao_existe('cliente',            'idx_cliente_cpf',          'cpf');
CALL criar_indice_se_nao_existe('cliente',            'idx_cliente_telefone',     'telefone');

CALL criar_indice_se_nao_existe('pet',                'idx_pet_estab',            'estabelecimento_id');
CALL criar_indice_se_nao_existe('pet',                'idx_pet_dono',             'dono_id');
CALL criar_indice_se_nao_existe('pet',                'idx_pet_estab_status',     'estabelecimento_id, status');
CALL criar_indice_se_nao_existe('pet',                'idx_pet_nome',             'nome');

-- ── Banho & Tosa ──────────────────────────────────────
CALL criar_indice_se_nao_existe('agendamento',        'idx_agend_estab_data',     'estabelecimento_id, data');
CALL criar_indice_se_nao_existe('agendamento',        'idx_agend_status',         'concluido');
CALL criar_indice_se_nao_existe('agendamento_pet_servico', 'idx_aps_agend',       'agendamentoId');
CALL criar_indice_se_nao_existe('agendamento_pet_servico', 'idx_aps_pet',         'petId');
CALL criar_indice_se_nao_existe('agendamento_pet_servico', 'idx_aps_servico',     'servicoId');

-- ── Consulta clínica ──────────────────────────────────
CALL criar_indice_se_nao_existe('consulta',           'idx_consulta_estab_data',  'estabelecimento_id, data');
CALL criar_indice_se_nao_existe('consulta',           'idx_consulta_estab_status','estabelecimento_id, status');
CALL criar_indice_se_nao_existe('consulta',           'idx_consulta_pet',         'pet_id');
CALL criar_indice_se_nao_existe('consulta',           'idx_consulta_cliente',     'cliente_id');
CALL criar_indice_se_nao_existe('consulta',           'idx_consulta_vet',         'veterinario_id');
CALL criar_indice_se_nao_existe('consulta',           'idx_consulta_tipo',        'tipo');
CALL criar_indice_se_nao_existe('consulta_pet',       'idx_consulta_pet_consulta','consulta_id');
CALL criar_indice_se_nao_existe('consulta_pet',       'idx_consulta_pet_pet',     'pet_id');

-- ── Internação ────────────────────────────────────────
CALL criar_indice_se_nao_existe('internacao',              'idx_int_estab',        'estabelecimento_id');
CALL criar_indice_se_nao_existe('internacao',              'idx_int_pet',          'pet_id');
CALL criar_indice_se_nao_existe('internacao',              'idx_int_status',       'status');
CALL criar_indice_se_nao_existe('internacao_evento',       'idx_int_evento_int',   'internacao_id');
CALL criar_indice_se_nao_existe('internacao_evento',       'idx_int_evento_data',  'dataHora');
CALL criar_indice_se_nao_existe('internacao_execucao',     'idx_int_exec_prescricao','prescricao_id');
CALL criar_indice_se_nao_existe('internacao_execucao',     'idx_int_exec_vet',     'veterinario_id');
CALL criar_indice_se_nao_existe('internacao_prescricao',   'idx_int_pres_int',     'internacao_id');

-- ── Vacina / Prontuário / Peso ────────────────────────
CALL criar_indice_se_nao_existe('vacina',             'idx_vacina_estab_pet',     'estabelecimento_id, pet_id');
CALL criar_indice_se_nao_existe('vacina',             'idx_vacina_validade',      'data_validade');
CALL criar_indice_se_nao_existe('prontuariopet',      'idx_prontuario_pet',       'pet_id');
CALL criar_indice_se_nao_existe('peso_pet',           'idx_peso_pet',             'pet_id');

-- ── Veterinário / Serviço / Produto / Estoque ─────────
CALL criar_indice_se_nao_existe('veterinario',        'idx_vet_estab',            'estabelecimento_id');
CALL criar_indice_se_nao_existe('servico',            'idx_servico_estab',        'estabelecimento_id');
CALL criar_indice_se_nao_existe('servico',            'idx_servico_tipo',         'tipo');
CALL criar_indice_se_nao_existe('produto',            'idx_produto_estab',        'estabelecimento_id');
CALL criar_indice_se_nao_existe('produto',            'idx_produto_status',       'status');
CALL criar_indice_se_nao_existe('produto',            'idx_produto_categoria',    'categoria');
CALL criar_indice_se_nao_existe('produto',            'idx_produto_codbar',       'codigo_barras');
CALL criar_indice_se_nao_existe('estoque',            'idx_estoque_prod',         'produto_id');
CALL criar_indice_se_nao_existe('estoque_movimento',  'idx_estoque_mov_prod',     'produto_id');
CALL criar_indice_se_nao_existe('estoque_movimento',  'idx_estoque_mov_data',     'data');

-- ── Hospedagem ────────────────────────────────────────
CALL criar_indice_se_nao_existe('hospedagem',         'idx_hosp_estab',           'estabelecimento_id');
CALL criar_indice_se_nao_existe('hospedagem_caes',    'idx_hosp_caes_hosp',       'hospedagem_id');
CALL criar_indice_se_nao_existe('hospedagem_caes',    'idx_hosp_caes_pet',        'pet_id');

-- ── Orçamento / Nota fiscal ───────────────────────────
CALL criar_indice_se_nao_existe('orcamento',          'idx_orc_estab_status',     'estabelecimento_id, status');
CALL criar_indice_se_nao_existe('orcamento',          'idx_orc_cliente',          'cliente_id');
CALL criar_indice_se_nao_existe('orcamento_item',     'idx_orc_item_orc',         'orcamento_id');
CALL criar_indice_se_nao_existe('nota_fiscal',        'idx_nf_venda',             'venda_id');
CALL criar_indice_se_nao_existe('nota_fiscal',        'idx_nf_status',            'status');

DROP PROCEDURE IF EXISTS criar_indice_se_nao_existe;
