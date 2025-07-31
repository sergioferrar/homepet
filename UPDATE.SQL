CREATE TABLE `agendamento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `data` datetime DEFAULT NULL,
  `concluido` int DEFAULT NULL,
  `pronto` int DEFAULT NULL,
  `horaChegada` datetime DEFAULT NULL,
  `metodo_pagamento` enum('dinheiro','pix','credito','debito','pendente','pacote_semanal_1','pacote_semanal_2','pacote_semanal_3','pacote_semanal_4','pacote_quinzenal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `horaSaida` datetime DEFAULT NULL,
  `taxi_dog` tinyint DEFAULT '0',
  `taxa_taxi_dog` decimal(10,2) DEFAULT NULL,
  `donoId` int DEFAULT NULL,
  `pacote_quinzenal` tinyint(1) DEFAULT '0',
  `pacote_semanal` tinyint(1) DEFAULT '0',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'aguardando',
  `pet_id` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=616 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `agendamento_clinica` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `data` datetime NOT NULL,
  `hora` time DEFAULT NULL,
  `procedimento` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'aguardando',
  `observacoes` text,
  `pet_id` int DEFAULT NULL,
  `dono_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `agendamento_pet_servico` (
  `agendamentoId` int NOT NULL,
  `petId` int NOT NULL,
  `servicoId` int NOT NULL,
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `status` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `cliente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rua` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `complemento` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bairro` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cidade` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cep` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=362 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `colaborador` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `data_admissao` date DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT '1',
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `consulta` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `cliente_id` int NOT NULL,
  `pet_id` int NOT NULL,
  `data` date NOT NULL,
  `hora` time NOT NULL,
  `observacoes` text COLLATE utf8mb4_general_ci,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('aguardando','atendido','cancelado') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'aguardando',
  `anamnese` text COLLATE utf8mb4_general_ci,
  `tipo` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_consulta_estabelecimento` (`estabelecimento_id`),
  KEY `idx_consulta_data` (`data`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `documento_modelo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `conteudo` text NOT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `cabecalho` text,
  `rodape` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `exame` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `pet_id` int DEFAULT NULL,
  `agendamento_id` int DEFAULT NULL,
  `descricao` text,
  `arquivo` text,
  `criado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `financeiro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `valor` decimal(10,0) DEFAULT NULL,
  `data` datetime DEFAULT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pet_id` int DEFAULT NULL,
  `pet_nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=488 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `financeiropendente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data` datetime NOT NULL,
  `pet_id` int DEFAULT NULL,
  `metodo_pagamento` enum('dinheiro','pix','credito','debito','pendente') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `agendamento_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `hospedagem_caes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `cliente_id` int DEFAULT NULL,
  `pet_id` int DEFAULT NULL,
  `data_entrada` date DEFAULT NULL,
  `data_saida` date DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `observacoes` text,
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  KEY `pet_id` (`pet_id`),
  CONSTRAINT `hospedagem_caes_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `cliente` (`id`),
  CONSTRAINT `hospedagem_caes_ibfk_2` FOREIGN KEY (`pet_id`) REFERENCES `pet` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `internacao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `data_inicio` date NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `status` enum('ativa','finalizada') NOT NULL DEFAULT 'ativa',
  `pet_id` int NOT NULL,
  `dono_id` int NOT NULL,
  `estabelecimento_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pet_id` (`pet_id`),
  KEY `dono_id` (`dono_id`),
  CONSTRAINT `internacao_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pet` (`id`),
  CONSTRAINT `internacao_ibfk_2` FOREIGN KEY (`dono_id`) REFERENCES `cliente` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `pet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `especie` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idade` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dono_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sexo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raca` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `porte` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=409 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `prontuario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `agendamento_id` int DEFAULT NULL,
  `observacoes` text,
  `arquivos` text,
  `criado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `prontuariopet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pet_id` int NOT NULL,
  `data` datetime NOT NULL,
  `tipo` enum('evolucao','procedimento','exame','vacina') NOT NULL,
  `descricao` text NOT NULL,
  `anexo` varchar(255) DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `receita` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `pet_id` int NOT NULL,
  `data` date NOT NULL,
  `resumo` varchar(255) DEFAULT NULL,
  `cabecalho` longtext,
  `conteudo` longtext,
  `rodape` longtext,
  `criado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `servico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor` decimal(10,0) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `vacina` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `pet_id` int DEFAULT NULL,
  `tipo` varchar(100) DEFAULT NULL,
  `data_aplicacao` date DEFAULT NULL,
  `data_validade` date DEFAULT NULL,
  `lote` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `veterinario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `especialidade` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
