CREATE
DATABASE homepet_000;

CREATE TABLE homepet_000.`agendamento`
(
    `id`               int(11) NOT NULL AUTO_INCREMENT,
    `data`             datetime       DEFAULT NULL,
    `pet_id`           int(11) DEFAULT NULL,
    `servico_id`       int(11) DEFAULT NULL,
    `concluido`        int(11) DEFAULT NULL,
    `pronto`           int(11) DEFAULT NULL,
    `horaChegada`      datetime       DEFAULT NULL,
    `metodo_pagamento` enum('dinheiro','pix','credito','debito','pendente') DEFAULT 'pendente',
    `horaSaida`        datetime       DEFAULT NULL,
    `taxi_dog`         tinyint(4) DEFAULT 0,
    `taxa_taxi_dog`    decimal(10, 2) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE homepet_000.`cliente`
(
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `nome`        varchar(255) DEFAULT NULL,
    `email`       varchar(255) DEFAULT NULL,
    `telefone`    varchar(255) DEFAULT NULL,
    `rua`         varchar(255) DEFAULT NULL,
    `numero`      int(11) DEFAULT NULL,
    `complemento` varchar(255) DEFAULT NULL,
    `bairro`      varchar(255) DEFAULT NULL,
    `cidade`      varchar(255) DEFAULT NULL,
    `whatsapp`    varchar(6) NOT NULL,
    `cep`         int(11) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE homepet_000.`financeiro`
(
    `id`        int(11) NOT NULL AUTO_INCREMENT,
    `descricao` varchar(255)   DEFAULT NULL,
    `valor`     decimal(10, 0) DEFAULT NULL,
    `data`      datetime       DEFAULT NULL,
    `pet_id`    int(11) DEFAULT NULL,
    `pet_nome`  varchar(255)   DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE homepet_000.`financeiropendente`
(
    `id`               int(11) NOT NULL AUTO_INCREMENT,
    `descricao`        varchar(255)   NOT NULL,
    `valor`            decimal(10, 2) NOT NULL,
    `data`             datetime       NOT NULL,
    `pet_id`           int(11) DEFAULT NULL,
    `metodo_pagamento` enum('dinheiro','pix','credito','debito','pendente') DEFAULT 'pendente',
    `agendamento_id`   int(11) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE homepet_000.`pet`
(
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `nome`        varchar(255) DEFAULT NULL,
    `tipo`        varchar(255) DEFAULT NULL,
    `idade`       varchar(255) DEFAULT NULL,
    `dono_id`     varchar(255) DEFAULT NULL,
    `especie`     varchar(255) DEFAULT NULL,
    `sexo`        varchar(255) DEFAULT NULL,
    `raca`        varchar(255) DEFAULT NULL,
    `porte`       varchar(255) DEFAULT NULL,
    `observacoes` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE homepet_000.`servico`
(
    `id`        int(11) NOT NULL AUTO_INCREMENT,
    `nome`      varchar(255)   DEFAULT NULL,
    `descricao` varchar(255)   DEFAULT NULL,
    `valor`     decimal(10, 0) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
