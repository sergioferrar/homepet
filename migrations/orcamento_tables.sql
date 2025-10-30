-- Tabela de Orçamentos
CREATE TABLE IF NOT EXISTS orcamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    cliente_id INT NULL,
    cliente_nome VARCHAR(255) NOT NULL,
    pet_id INT NULL,
    pet_nome VARCHAR(255) NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    desconto DECIMAL(10,2) NULL DEFAULT 0,
    valor_final DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pendente',
    data_criacao DATETIME NOT NULL,
    data_validade DATETIME NULL,
    observacoes TEXT NULL,
    INDEX idx_estabelecimento (estabelecimento_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_status (status),
    INDEX idx_data_criacao (data_criacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Itens do Orçamento
CREATE TABLE IF NOT EXISTS orcamento_item (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orcamento_id INT NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    tipo VARCHAR(100) NOT NULL,
    quantidade INT NOT NULL,
    valor_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    INDEX idx_orcamento (orcamento_id),
    FOREIGN KEY (orcamento_id) REFERENCES orcamento(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
