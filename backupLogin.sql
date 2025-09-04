-- MySQL dump 10.13  Distrib 8.0.43, for Linux (x86_64)
--
-- Host: localhost    Database: u199209817_login
-- ------------------------------------------------------
-- Server version	8.0.43-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `Agendamento`
--

DROP TABLE IF EXISTS `Agendamento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Agendamento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `data` datetime DEFAULT NULL,
  `concluido` tinyint(1) NOT NULL DEFAULT '0',
  `pronto` tinyint(1) NOT NULL DEFAULT '0',
  `horaChegada` datetime DEFAULT NULL,
  `metodo_pagamento` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `horaSaida` datetime DEFAULT NULL,
  `taxi_dog` tinyint(1) NOT NULL DEFAULT '0',
  `taxa_taxi_dog` decimal(10,2) DEFAULT NULL,
  `pacote_semanal` tinyint(1) NOT NULL DEFAULT '0',
  `pacote_quinzenal` tinyint(1) NOT NULL DEFAULT '0',
  `donoId` int DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aguardando',
  `estabelecimento_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Agendamento`
--

LOCK TABLES `Agendamento` WRITE;
/*!40000 ALTER TABLE `Agendamento` DISABLE KEYS */;
/*!40000 ALTER TABLE `Agendamento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Cliente`
--

DROP TABLE IF EXISTS `Cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Cliente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rua` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` int DEFAULT NULL,
  `complemento` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bairro` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cidade` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cep` int NOT NULL,
  `estabelecimento_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Cliente`
--

LOCK TABLES `Cliente` WRITE;
/*!40000 ALTER TABLE `Cliente` DISABLE KEYS */;
/*!40000 ALTER TABLE `Cliente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Financeiro`
--

DROP TABLE IF EXISTS `Financeiro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Financeiro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor` decimal(10,0) DEFAULT NULL,
  `data` datetime DEFAULT NULL,
  `pet_id` int DEFAULT NULL,
  `pet_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origem` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metodo_pagamento` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estabelecimento_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Financeiro`
--

LOCK TABLES `Financeiro` WRITE;
/*!40000 ALTER TABLE `Financeiro` DISABLE KEYS */;
/*!40000 ALTER TABLE `Financeiro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `agendamento_pet_servico`
--

DROP TABLE IF EXISTS `agendamento_pet_servico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agendamento_pet_servico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agendamentoId` int NOT NULL,
  `petId` int NOT NULL,
  `servicoId` int NOT NULL,
  `estabelecimento_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agendamento_pet_servico`
--

LOCK TABLES `agendamento_pet_servico` WRITE;
/*!40000 ALTER TABLE `agendamento_pet_servico` DISABLE KEYS */;
/*!40000 ALTER TABLE `agendamento_pet_servico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `box`
--

DROP TABLE IF EXISTS `box`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `box` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pet_id` int DEFAULT NULL,
  `numero` smallint unsigned NOT NULL,
  `ocupado` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `IDX_8A9483A966F7FB6` (`pet_id`),
  CONSTRAINT `FK_8A9483A966F7FB6` FOREIGN KEY (`pet_id`) REFERENCES `pet` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `box`
--

LOCK TABLES `box` WRITE;
/*!40000 ALTER TABLE `box` DISABLE KEYS */;
/*!40000 ALTER TABLE `box` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consulta`
--

DROP TABLE IF EXISTS `consulta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consulta` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `cliente_id` int NOT NULL,
  `pet_id` int NOT NULL,
  `data` date NOT NULL,
  `hora` time NOT NULL,
  `observacoes` longtext COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aguardando',
  `criado_em` datetime NOT NULL,
  `anamnese` longtext COLLATE utf8mb4_unicode_ci,
  `tipo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consulta`
--

LOCK TABLES `consulta` WRITE;
/*!40000 ALTER TABLE `consulta` DISABLE KEYS */;
/*!40000 ALTER TABLE `consulta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctrine_migration_versions`
--

DROP TABLE IF EXISTS `doctrine_migration_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) COLLATE utf8mb3_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctrine_migration_versions`
--

LOCK TABLES `doctrine_migration_versions` WRITE;
/*!40000 ALTER TABLE `doctrine_migration_versions` DISABLE KEYS */;
INSERT INTO `doctrine_migration_versions` VALUES ('DoctrineMigrations\\Version20250222020424',NULL,NULL),('DoctrineMigrations\\Version20250527232010',NULL,NULL),('DoctrineMigrations\\Version20250608023500',NULL,NULL),('DoctrineMigrations\\Version20250614232746',NULL,NULL),('DoctrineMigrations\\Version20250801034039',NULL,NULL),('DoctrineMigrations\\Version20250801034424',NULL,NULL);
/*!40000 ALTER TABLE `doctrine_migration_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estabelecimento`
--

DROP TABLE IF EXISTS `estabelecimento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estabelecimento`
--

LOCK TABLES `estabelecimento` WRITE;
/*!40000 ALTER TABLE `estabelecimento` DISABLE KEYS */;
INSERT INTO `estabelecimento` VALUES (1,'dog amado','39.798.972/0001-09','Av Americo Vespucio','913','','','Belo Horizonte','brasil',31230240,'Ativo','2025-03-05 00:33:53','2025-03-07 12:06:52',1,'2025-04-05 00:00:00','2027-05-20 00:00:00'),(25,'Adilio Gobira Pet Shop Liberdade','45343543543434','Av. Alberto Calixto','10000','loja 2','Liberdade','Santa Luzia','brasil',33170863,'Ativo','2025-03-07 12:06:52','2025-03-07 12:06:52',1,'2025-05-19 13:43:31','2026-06-18 13:43:31');
/*!40000 ALTER TABLE `estabelecimento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financeiropendente`
--

DROP TABLE IF EXISTS `financeiropendente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financeiropendente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data` datetime NOT NULL,
  `pet_id` int DEFAULT NULL,
  `metodo_pagamento` enum('dinheiro','pix','credito','debito','pendente') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agendamento_id` int DEFAULT NULL,
  `estabelecimento_id` int NOT NULL,
  `origem` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financeiropendente`
--

LOCK TABLES `financeiropendente` WRITE;
/*!40000 ALTER TABLE `financeiropendente` DISABLE KEYS */;
/*!40000 ALTER TABLE `financeiropendente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grupo`
--

DROP TABLE IF EXISTS `grupo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grupo`
--

LOCK TABLES `grupo` WRITE;
/*!40000 ALTER TABLE `grupo` DISABLE KEYS */;
/*!40000 ALTER TABLE `grupo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hospedagem_caes`
--

DROP TABLE IF EXISTS `hospedagem_caes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hospedagem_caes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cliente_id` int NOT NULL,
  `pet_id` int NOT NULL,
  `data_entrada` datetime NOT NULL,
  `data_saida` datetime NOT NULL,
  `valor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacoes` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `estabelecimento_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hospedagem_caes`
--

LOCK TABLES `hospedagem_caes` WRITE;
/*!40000 ALTER TABLE `hospedagem_caes` DISABLE KEYS */;
/*!40000 ALTER TABLE `hospedagem_caes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internacao`
--

DROP TABLE IF EXISTS `internacao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `internacao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `data_inicio` datetime NOT NULL,
  `motivo` longtext COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pet_id` int NOT NULL,
  `dono_id` int DEFAULT NULL,
  `estabelecimento_id` int NOT NULL,
  `situacao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `risco` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `box` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alta_prevista` date DEFAULT NULL,
  `diagnostico` longtext COLLATE utf8mb4_unicode_ci,
  `prognostico` longtext COLLATE utf8mb4_unicode_ci,
  `anotacoes` longtext COLLATE utf8mb4_unicode_ci,
  `veterinario_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internacao`
--

LOCK TABLES `internacao` WRITE;
/*!40000 ALTER TABLE `internacao` DISABLE KEYS */;
/*!40000 ALTER TABLE `internacao` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internacao_evento`
--

DROP TABLE IF EXISTS `internacao_evento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `internacao_evento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `internacao_id` int NOT NULL,
  `pet_id` int NOT NULL,
  `tipo` enum('internacao','alta','ocorrencia','peso','prescricao','medicacao_exec') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` longtext COLLATE utf8mb4_unicode_ci,
  `data_hora` datetime NOT NULL,
  `criado_em` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internacao_evento`
--

LOCK TABLES `internacao_evento` WRITE;
/*!40000 ALTER TABLE `internacao_evento` DISABLE KEYS */;
/*!40000 ALTER TABLE `internacao_evento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu`
--

DROP TABLE IF EXISTS `menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu`
--

LOCK TABLES `menu` WRITE;
/*!40000 ALTER TABLE `menu` DISABLE KEYS */;
INSERT INTO `menu` VALUES (1,'Agendamento',NULL,'Toda a listagem de agendamentos do sistema é localizado nesse menu.','#','ativo','person-vcard',1,1),(2,'Novo Agendamento',1,'Cadastrar novo agendamento','agendamento_novo','ativo','circle',1,1),(4,'Clientes',NULL,'Listagem de clientes.','cliente_index','ativo','person-circle',NULL,2),(5,'Pets',NULL,'Listagem de pets','#','ativo','shop',NULL,3),(6,'Ver todos',5,'sfadfasd','pet_index','ativo','circle',NULL,3),(7,'Novo Pet',5,'cadastro de pets','pet_novo','ativo','circle',NULL,3),(8,'Financeiro',NULL,'Financeiro','#','ativo','currency-dollar',NULL,5),(9,'Financeiro Diário',8,'Financeiro Diário','financeiro_index','ativo','circle',NULL,5),(13,'Financeiro Pendente',8,'Financeiro Diários','financeiro_index','ativo','circle',NULL,5),(14,'Relatorio Financeiro ',8,'Financeiro Diáriossss','financeiro_index','ativo','circle',NULL,5),(15,'Quadro de Banho & Tosa',NULL,'laskdfj','agendamento_quadro','ativo','kanban',NULL,7),(16,'Clínica veterinária',NULL,'asdçfk','#','ativo','hospital',NULL,9),(17,'Dashboard',16,'sdfasd','clinica_dashboard','ativo','circle',NULL,9),(18,'Nova Consulta',16,'asdfasd','clinica_nova_consulta','ativo','circle',NULL,9),(19,'Hospedagem de Cães',NULL,'asdf','#','ativo','journal-text',NULL,8),(20,'Novo Agendamento',19,'Novo Agendamento','hospedagem_agendar','ativo','circle',NULL,8),(21,'Nova Hospedagem',19,'sdf','hospedagem_listar','ativo','circle',NULL,8),(22,'Usuários',NULL,'zcx','app_usuario','ativo','person',NULL,6),(23,'Ver todos',1,'listagem de todos os agendamentos.','agendamento_index','ativo','alarm-fill',NULL,1),(30,'Serviços ',NULL,'','servico_index','ativo','server',NULL,4);
/*!40000 ALTER TABLE `menu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_grupo_modulo`
--

DROP TABLE IF EXISTS `menu_grupo_modulo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_grupo_modulo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_menu` int NOT NULL,
  `id_grupo` int NOT NULL,
  `id_modulo` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_grupo_modulo`
--

LOCK TABLES `menu_grupo_modulo` WRITE;
/*!40000 ALTER TABLE `menu_grupo_modulo` DISABLE KEYS */;
/*!40000 ALTER TABLE `menu_grupo_modulo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modulo`
--

DROP TABLE IF EXISTS `modulo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modulo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `trial` int DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modulo`
--

LOCK TABLES `modulo` WRITE;
/*!40000 ALTER TABLE `modulo` DISABLE KEYS */;
INSERT INTO `modulo` VALUES (1,'Agendamentos de Pets',0,'Ativo','agendamentosDePets'),(2,'Cadastro de Clientes',0,'Ativo','cadastroDeClientes'),(3,'Cadastro de Pets',0,'Ativo','cadastroDePets'),(4,'Serviços do Petshop',0,'Ativo','serviçosDoPetshop'),(5,'Área de Financeiro',0,'Ativo','áreaDeFinanceiro'),(6,'Gestão de Usuários',0,'Ativo','gestãoDeUsuários'),(7,'Banho e Tosa',0,'Ativo','banhoETosa'),(8,'Hospedagem de Cães',0,'Ativo','hospedagemDeCães'),(9,'Clínica Veterinária',0,'Ativo','clínicaVeterinária');
/*!40000 ALTER TABLE `modulo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pet`
--

DROP TABLE IF EXISTS `pet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `especie` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idade` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dono_id` int DEFAULT NULL,
  `sexo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raca` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `porte` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=409 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pet`
--

LOCK TABLES `pet` WRITE;
/*!40000 ALTER TABLE `pet` DISABLE KEYS */;
/*!40000 ALTER TABLE `pet` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planos`
--

DROP TABLE IF EXISTS `planos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planos`
--

LOCK TABLES `planos` WRITE;
/*!40000 ALTER TABLE `planos` DISABLE KEYS */;
INSERT INTO `planos` VALUES (1,'Plano Full Administrador','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\",\"hospedagemDeC\\u00e3es\",\"cl\\u00ednicaVeterin\\u00e1ria\"]',0.00,'Inativo',0,NULL,'2025-04-29 22:58:35','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\",\"hospedagemDeC\\u00e3es\",\"cl\\u00ednicaVeterin\\u00e1ria\"]'),(2,'Plano básico','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\"]',74.90,'Ativo',0,NULL,'2025-05-03 10:17:47','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\"]'),(3,'Plano intermediário','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\",\"hospedagemDeC\\u00e3es\"]',95.90,'Ativo',1,NULL,'2025-05-03 10:18:03','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\",\"hospedagemDeC\\u00e3es\"]'),(4,'Plano Avançado','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\",\"hospedagemDeC\\u00e3es\",\"cl\\u00ednicaVeterin\\u00e1ria\"]',119.90,'Ativo',0,NULL,'2025-05-03 10:18:19','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\",\"hospedagemDeC\\u00e3es\",\"cl\\u00ednicaVeterin\\u00e1ria\"]');
/*!40000 ALTER TABLE `planos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receita`
--

DROP TABLE IF EXISTS `receita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `receita` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estabelecimento_id` int NOT NULL,
  `pet_id` int NOT NULL,
  `data` date NOT NULL,
  `resumo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cabecalho` longtext COLLATE utf8mb4_unicode_ci,
  `conteudo` longtext COLLATE utf8mb4_unicode_ci,
  `rodape` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receita`
--

LOCK TABLES `receita` WRITE;
/*!40000 ALTER TABLE `receita` DISABLE KEYS */;
/*!40000 ALTER TABLE `receita` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servico`
--

DROP TABLE IF EXISTS `servico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor` decimal(10,0) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servico`
--

LOCK TABLES `servico` WRITE;
/*!40000 ALTER TABLE `servico` DISABLE KEYS */;
/*!40000 ALTER TABLE `servico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,'admin','$2y$13$LCc51Y2fUCLcDBux3MrcT.TEYEj.SxUKowQ5VGZSx1iBx7GbJL.zy','sergioferrari150395@gmail.com','[\"ROLE_ADMIN\"]','Super Admin',1,NULL),(4,'Lucenir Monteiro','$2y$13$l5GfkqwPGB7ADHHkx3Q7BOwZBcI2YCHsXf6R085RSoGHV2rKsPC1y','lucenirmonteiro@gmail.com','[\"ROLE_ADMIN\"]','Admin',1,NULL),(6,'Adilio Gobira','$2y$13$5to5VkD9stvrya3w/NNnTeKkj0Wn4mkbQygQ.6npOy0MecNxNLraa','adiliogobira@gmail.com','[\"ROLE_ADMIN\"]','Admin',25,NULL);
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `veterinario`
--

DROP TABLE IF EXISTS `veterinario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `veterinario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `especialidade` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estabelecimento_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `veterinario`
--

LOCK TABLES `veterinario` WRITE;
/*!40000 ALTER TABLE `veterinario` DISABLE KEYS */;
/*!40000 ALTER TABLE `veterinario` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-04 14:00:59
