CREATE TABLE IF NOT EXISTS box (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    numero VARCHAR(20) NOT NULL,
    tipo ENUM('pequeno', 'medio', 'grande') NOT NULL,
    localizacao VARCHAR(100),
    status ENUM('disponivel', 'ocupado', 'manutencao', 'reservado') DEFAULT 'disponivel',
    capacidade INT DEFAULT 1,
    valor_diaria DECIMAL(10,2) NOT NULL,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estabelecimento (estabelecimento_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_box (estabelecimento_id, numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE box
    -- Porte do animal suportado
    ADD COLUMN IF NOT EXISTS porte ENUM('pequeno','medio','grande','gigante','todos') NOT NULL DEFAULT 'todos' AFTER tipo,

    -- Estrutura física da unidade
    ADD COLUMN IF NOT EXISTS estrutura ENUM('maca','canil','gaiola','cercado','baia') NOT NULL DEFAULT 'canil' AFTER porte,

    -- Recursos de suporte clínico
    ADD COLUMN IF NOT EXISTS suporte_soro     TINYINT(1) NOT NULL DEFAULT 0 AFTER localizacao,
    ADD COLUMN IF NOT EXISTS suporte_oxigenio TINYINT(1) NOT NULL DEFAULT 0 AFTER suporte_soro,
    ADD COLUMN IF NOT EXISTS tem_aquecimento  TINYINT(1) NOT NULL DEFAULT 0 AFTER suporte_oxigenio,
    ADD COLUMN IF NOT EXISTS tem_camera       TINYINT(1) NOT NULL DEFAULT 0 AFTER tem_aquecimento,

    -- Limites físicos
    ADD COLUMN IF NOT EXISTS peso_maximo_kg DECIMAL(5,1) DEFAULT NULL AFTER tem_camera,

    -- Ajuste no ENUM de status: adiciona 'higienizacao'
    MODIFY COLUMN status ENUM('disponivel','ocupado','manutencao','reservado','higienizacao') NOT NULL DEFAULT 'disponivel',

    -- Ajuste no ENUM de tipo: adiciona finalidades clínicas
    MODIFY COLUMN tipo ENUM('internacao','emergencia','observacao','isolamento','cirurgia','recuperacao') NOT NULL,

    -- valor_diaria agora é opcional
    MODIFY COLUMN valor_diaria DECIMAL(10,2) DEFAULT NULL;

-- Índice de busca por status (para listagem rápida de disponíveis)
CREATE INDEX IF NOT EXISTS idx_box_status ON box (estabelecimento_id, status);