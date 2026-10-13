-- =====================================================
-- Crédito do cliente — rodar em cada tenant (homepet_XX)
-- =====================================================

CREATE TABLE IF NOT EXISTS cliente_credito_movimento (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id  INT NOT NULL,
    cliente_id          INT NOT NULL,
    pet_id              INT NULL,
    tipo                ENUM('entrada','saida') NOT NULL,
    valor               DECIMAL(10,2) NOT NULL,
    descricao           VARCHAR(255) NULL,
    origem              VARCHAR(60)  NULL,
    origem_id           INT          NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    criado_por          INT NULL,
    KEY idx_ccm_cliente (estabelecimento_id, cliente_id),
    KEY idx_ccm_pet     (pet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
