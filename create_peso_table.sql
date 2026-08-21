-- Para homepet_1
USE homepet_1;
CREATE TABLE IF NOT EXISTS peso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    estabelecimento_id INT NOT NULL,
    peso DECIMAL(5,2) NOT NULL,
    data DATE NOT NULL,
    hora TIME,
    observacoes TEXT,
    veterinario_id INT,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pet_estabelecimento (pet_id, estabelecimento_id),
    INDEX idx_data (data DESC)
);

-- Para homepet_26 (caso seja necessário)
USE homepet_26;
CREATE TABLE IF NOT EXISTS peso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    estabelecimento_id INT NOT NULL,
    peso DECIMAL(5,2) NOT NULL,
    data DATE NOT NULL,
    hora TIME,
    observacoes TEXT,
    veterinario_id INT,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pet_estabelecimento (pet_id, estabelecimento_id),
    INDEX idx_data (data DESC)
);