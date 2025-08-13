CREATE TABLE `estabelecimento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `razaoSocial` varchar(255) NOT NULL,
  `cnpj` varchar(20) NOT NULL,
  `rua` varchar(255) NOT NULL,
  `numero` varchar(60) NOT NULL,
  `complemento` varchar(255) DEFAULT NULL,
  `bairro` varchar(255) NOT NULL,
  `cidade` varchar(255) NOT NULL,
  `pais` varchar(255) DEFAULT NULL,
  `cep` int NOT NULL,
  `status` varchar(25) NOT NULL,
  `dataCadastro` timestamp NULL DEFAULT NULL,
  `dataAtualizacao` timestamp NULL DEFAULT NULL,
  `planoId` int NOT NULL,
  `dataPlanoInicio` datetime NOT NULL,
  `dataPlanoFim` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE `grupo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;


CREATE TABLE `menu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent` int DEFAULT NULL,
  `descricao` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rota` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordem` int DEFAULT NULL,
  `modulo` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;



CREATE TABLE `menu_grupo_modulo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_menu` int NOT NULL,
  `id_grupo` int NOT NULL,
  `id_modulo` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;



CREATE TABLE `modulo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trial` int DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;



CREATE TABLE `planos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `valor` decimal(10,2) DEFAULT NULL,
  `status` enum('Ativo','Inativo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Inativo',
  `trial` int DEFAULT NULL,
  `dataTrial` timestamp NULL DEFAULT NULL,
  `dataPlano` datetime DEFAULT NULL,
  `modulos` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;



CREATE TABLE `usuario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome_usuario` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `email` varchar(45) NOT NULL,
  `roles` varchar(45) NOT NULL DEFAULT '["ROLE_ADMIN"]',
  `access_level` enum('Super Admin','Admin','Atendente','Balconista') DEFAULT NULL,
  `petshop_id` bigint NOT NULL,
  `data_trial` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome_usuario` (`nome_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;





INSERT INTO menu (id, titulo, parent, descricao, rota, status, icone, ordem, modulo)
VALUES
('1', 'Agendamento', NULL, 'Toda a listagem de agendamentos do sistema é localizado nesse menu.', '#', 'ativo', 'person-vcard', '1', '1',
'2', 'Novo Agendamento', '1', 'Cadastrar novo agendamento', 'agendamento_novo', 'ativo', 'circle', '1', '1',
'4', 'Clientes', NULL, 'Listagem de clientes.', 'cliente_index', 'ativo', 'person-circle', NULL, '2',
'5', 'Pets', NULL, 'Listagem de pets', '#', 'ativo', 'shop', NULL, '3',
'6', 'Ver todos', '5', 'sfadfasd', 'pet_index', 'ativo', 'circle', NULL, '3',
'7', 'Novo Pet', '5', 'cadastro de pets', 'pet_novo', 'ativo', 'circle', NULL, '3',
'8', 'Financeiro', NULL, 'Financeiro', '#', 'ativo', 'currency-dollar', NULL, '5',
'9', 'Financeiro Diário', '8', 'Financeiro Diário', 'financeiro_index', 'ativo', 'circle', NULL, '5',
'13', 'Financeiro Pendente', '8', 'Financeiro Diários', 'financeiro_index', 'ativo', 'circle', NULL, '5',
'14', 'Relatorio Financeiro ', '8', 'Financeiro Diáriossss', 'financeiro_index', 'ativo', 'circle', NULL, '5',
'15', 'Quadro de Banho & Tosa', NULL, 'laskdfj', 'agendamento_quadro', 'ativo', 'kanban', NULL, '7',
'16', 'Clínica veterinária', NULL, 'asdçfk', '#', 'ativo', 'hospital', NULL, '9',
'17', 'Dashboard', '16', 'sdfasd', 'clinica_dashboard', 'ativo', 'circle', NULL, '9',
'18', 'Nova Consulta', '16', 'asdfasd', 'clinica_nova_consulta', 'ativo', 'circle', NULL, '9',
'19', 'Hospedagem de Cães', NULL, 'asdf', '#', 'ativo', 'journal-text', NULL, '8',
'20', 'Novo Agendamento', '19', 'Novo Agendamento', 'hospedagem_agendar', 'ativo', 'circle', NULL, '8',
'21', 'Nova Hospedagem', '19', 'sdf', 'hospedagem_listar', 'ativo', 'circle', NULL, '8',
'22', 'Usuários', NULL, 'zcx', 'app_usuario', 'ativo', 'person', NULL, '6',
'23', 'Ver todos', '1', 'listagem de todos os agendamentos.', 'agendamento_index', 'ativo', 'alarm-fill', NULL, '1',
'30', 'Serviços ', NULL, '', 'servico_index', 'ativo', 'server', NULL, '4');