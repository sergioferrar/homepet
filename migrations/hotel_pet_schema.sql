-- =====================================================
-- SISTEMA DE HOSPEDAGEM DE CÃES (HOTEL PET)
-- Migration completa com todas as tabelas
-- =====================================================

-- Tabela de Boxes
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

-- Tabela de Reservas
CREATE TABLE IF NOT EXISTS reserva (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    pet_id INT NOT NULL,
    cliente_id INT NOT NULL,
    box_id INT,
    data_entrada DATE NOT NULL,
    data_saida DATE NOT NULL,
    valor_estimado DECIMAL(10,2),
    status ENUM('pendente', 'confirmada', 'em_andamento', 'concluida', 'cancelada') DEFAULT 'pendente',
    pacote_id INT,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estabelecimento (estabelecimento_id),
    INDEX idx_pet (pet_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_box (box_id),
    INDEX idx_datas (data_entrada, data_saida),
    INDEX idx_status (status),
    FOREIGN KEY (box_id) REFERENCES box(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Hospedagens
CREATE TABLE IF NOT EXISTS hospedagem (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    pet_id INT NOT NULL,
    cliente_id INT NOT NULL,
    box_id INT NOT NULL,
    reserva_id INT,
    data_entrada DATETIME NOT NULL,
    data_saida_prevista DATE NOT NULL,
    data_saida_real DATETIME,
    valor_diaria DECIMAL(10,2) NOT NULL,
    valor_servicos DECIMAL(10,2) DEFAULT 0,
    valor_total DECIMAL(10,2),
    status ENUM('ativa', 'concluida', 'cancelada') DEFAULT 'ativa',
    peso_entrada DECIMAL(5,2),
    peso_saida DECIMAL(5,2),
    comportamento_entrada TEXT,
    comportamento_saida TEXT,
    observacoes_entrada TEXT,
    observacoes_saida TEXT,
    instrucoes_especiais TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estabelecimento (estabelecimento_id),
    INDEX idx_pet (pet_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_box (box_id),
    INDEX idx_status (status),
    INDEX idx_datas (data_entrada, data_saida_real),
    FOREIGN KEY (box_id) REFERENCES box(id),
    FOREIGN KEY (reserva_id) REFERENCES reserva(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Alimentação
CREATE TABLE IF NOT EXISTS hospedagem_alimentacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    hospedagem_id INT NOT NULL,
    data_hora_programada DATETIME NOT NULL,
    data_hora_realizada DATETIME,
    tipo_racao VARCHAR(100),
    quantidade VARCHAR(50),
    responsavel_id INT,
    observacoes TEXT,
    status ENUM('pendente', 'realizada', 'cancelada') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hospedagem (hospedagem_id),
    INDEX idx_data_programada (data_hora_programada),
    INDEX idx_status (status),
    FOREIGN KEY (hospedagem_id) REFERENCES hospedagem(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Medicação (Prescrição)
CREATE TABLE IF NOT EXISTS hospedagem_medicacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    hospedagem_id INT NOT NULL,
    medicamento VARCHAR(200) NOT NULL,
    dosagem VARCHAR(100) NOT NULL,
    frequencia_horas INT NOT NULL,
    data_hora_inicio DATETIME NOT NULL,
    data_hora_fim DATETIME NOT NULL,
    observacoes TEXT,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hospedagem (hospedagem_id),
    INDEX idx_ativo (ativo),
    FOREIGN KEY (hospedagem_id) REFERENCES hospedagem(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Administração de Medicação
CREATE TABLE IF NOT EXISTS hospedagem_medicacao_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    medicacao_id INT NOT NULL,
    data_hora_programada DATETIME NOT NULL,
    data_hora_realizada DATETIME,
    responsavel_id INT,
    observacoes TEXT,
    status ENUM('pendente', 'administrada', 'cancelada') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_medicacao (medicacao_id),
    INDEX idx_data_programada (data_hora_programada),
    INDEX idx_status (status),
    FOREIGN KEY (medicacao_id) REFERENCES hospedagem_medicacao(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Atividades
CREATE TABLE IF NOT EXISTS hospedagem_atividade (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    hospedagem_id INT NOT NULL,
    tipo ENUM('banho', 'tosa', 'passeio', 'recreacao', 'veterinario', 'outro') NOT NULL,
    descricao VARCHAR(200),
    data_hora DATETIME NOT NULL,
    valor DECIMAL(10,2) DEFAULT 0,
    status ENUM('agendada', 'realizada', 'cancelada') DEFAULT 'agendada',
    responsavel_id INT,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hospedagem (hospedagem_id),
    INDEX idx_tipo (tipo),
    INDEX idx_status (status),
    INDEX idx_data (data_hora),
    FOREIGN KEY (hospedagem_id) REFERENCES hospedagem(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Fotos
CREATE TABLE IF NOT EXISTS hospedagem_foto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    hospedagem_id INT NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    legenda TEXT,
    tipo ENUM('entrada', 'durante', 'saida', 'atividade') DEFAULT 'durante',
    data_hora DATETIME NOT NULL,
    compartilhada BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hospedagem (hospedagem_id),
    INDEX idx_tipo (tipo),
    FOREIGN KEY (hospedagem_id) REFERENCES hospedagem(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Pacotes
CREATE TABLE IF NOT EXISTS pacote (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estabelecimento_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    valor DECIMAL(10,2) NOT NULL,
    servicos_inclusos TEXT,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estabelecimento (estabelecimento_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir boxes de exemplo
INSERT INTO box (estabelecimento_id, numero, tipo, localizacao, valor_diaria, observacoes) VALUES
(1, 'B01', 'pequeno', 'Ala A', 50.00, 'Box para cães de pequeno porte'),
(1, 'B02', 'pequeno', 'Ala A', 50.00, 'Box para cães de pequeno porte'),
(1, 'B03', 'medio', 'Ala B', 80.00, 'Box para cães de médio porte'),
(1, 'B04', 'medio', 'Ala B', 80.00, 'Box para cães de médio porte'),
(1, 'B05', 'grande', 'Ala C', 120.00, 'Box para cães de grande porte'),
(1, 'B06', 'grande', 'Ala C', 120.00, 'Box para cães de grande porte');

-- Inserir pacotes de exemplo
INSERT INTO pacote (estabelecimento_id, nome, descricao, valor, servicos_inclusos) VALUES
(1, 'Pacote Básico', 'Hospedagem com alimentação padrão', 50.00, 'Hospedagem, Alimentação 2x ao dia'),
(1, 'Pacote Conforto', 'Hospedagem com alimentação e 1 banho', 80.00, 'Hospedagem, Alimentação 3x ao dia, 1 Banho'),
(1, 'Pacote Premium', 'Hospedagem completa com todos os serviços', 150.00, 'Hospedagem, Alimentação 3x ao dia, Banho, Tosa, Passeios diários, Fotos diárias');
