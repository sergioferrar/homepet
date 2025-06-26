-- MySQL dump 10.13  Distrib 8.0.42, for Linux (x86_64)
--
-- Host: localhost    Database: u199209817_login
-- ------------------------------------------------------
-- Server version	8.0.42-0ubuntu0.24.04.1

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
-- Table structure for table `colaborador`
--

DROP TABLE IF EXISTS `colaborador`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `colaborador`
--

LOCK TABLES `colaborador` WRITE;
/*!40000 ALTER TABLE `colaborador` DISABLE KEYS */;
/*!40000 ALTER TABLE `colaborador` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estabelecimento`
--

DROP TABLE IF EXISTS `estabelecimento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `estabelecimento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `razaoSocial` varchar(300) DEFAULT NULL,
  `cnpj` varchar(25) DEFAULT NULL,
  `rua` varchar(255) DEFAULT NULL,
  `numero` varchar(45) DEFAULT NULL,
  `complemento` varchar(200) DEFAULT NULL,
  `bairro` varchar(255) DEFAULT NULL,
  `cidade` varchar(255) DEFAULT NULL,
  `pais` varchar(200) DEFAULT NULL,
  `cep` int DEFAULT NULL,
  `status` enum('Ativo','Suspenso','Inativo') NOT NULL DEFAULT 'Inativo',
  `dataCadastro` timestamp NULL DEFAULT NULL,
  `dataAtualizacao` timestamp NULL DEFAULT NULL,
  `planoId` int DEFAULT NULL,
  `dataPlanoInicio` datetime DEFAULT NULL,
  `dataPlanoFim` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estabelecimento`
--

LOCK TABLES `estabelecimento` WRITE;
/*!40000 ALTER TABLE `estabelecimento` DISABLE KEYS */;
INSERT INTO `estabelecimento` VALUES (1,'dog amado','39.798.972/0001-09','Av Americo Vespucio','913','','','Belo Horizonte','brasil',31230240,'Ativo','2025-03-05 00:33:53','2025-03-07 12:06:52',1,'2025-04-05 00:00:00','2026-05-20 00:00:00'),(25,'Adilio Gobira Pet Shop Liberdade','45343543543434','Av. Alberto Calixto','10000','loja 2','Liberdade','Santa Luzia','brasil',33170863,'Ativo','2025-03-07 12:06:52','2025-03-07 12:06:52',1,'2025-05-19 13:43:31','2025-06-18 13:43:31');
/*!40000 ALTER TABLE `estabelecimento` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu`
--

LOCK TABLES `menu` WRITE;
/*!40000 ALTER TABLE `menu` DISABLE KEYS */;
INSERT INTO `menu` VALUES (1,'Agendamento',NULL,'Toda a listagem de agendamentos do sistema é localizado nesse menu.','#','ativo','person-vcard',1,1),(2,'Novo Agendamento',1,'Cadastrar novo agendamento','agendamento_novo','ativo','circle',1,1),(4,'Clientes',NULL,'Listagem de clientes.','cliente_index','ativo','person-circle',NULL,2),(5,'Pets',NULL,'Listagem de pets','#','ativo','shop',NULL,3),(6,'Ver todos',5,'sfadfasd','pet_index','ativo','circle',NULL,3),(7,'Novo Pet',5,'cadastro de pets','pet_novo','ativo','circle',NULL,3),(8,'Financeiro',NULL,'Financeiro','#','ativo','currency-dollar',NULL,5),(9,'Financeiro Diário',8,'Financeiro Diário','financeiro_index','ativo','circle',NULL,5),(13,'Financeiro Pendente',8,'Financeiro Diários','financeiro_pendente','ativo','circle',NULL,5),(14,'Financeiro Pendente',8,'Financeiro Diáriossss','financeiro_relatorio','ativo','circle',NULL,5),(15,'Quadro de Banho & Tosa',NULL,'laskdfj','agendamento_quadro','ativo','kanban',NULL,7),(16,'Clínica veterinária',NULL,'asdçfk','#','ativo','hospital',NULL,9),(17,'Dashboard',16,'sdfasd','clinica_dashboard','ativo','circle',NULL,9),(18,'Nova Consulta',16,'asdfasd','clinica_nova_consulta','ativo','circle',NULL,9),(19,'Hospedagem de Cães',NULL,'asdf','#','ativo','journal-text',NULL,8),(20,'Novo Agendamento',19,'Novo Agendamento','hospedagem_agendar','ativo','circle',NULL,8),(21,'Nova Hospedagem',19,'sdf','hospedagem_listar','ativo','circle',NULL,8),(22,'Usuários',NULL,'zcx','app_usuario','ativo','person',NULL,6),(23,'Ver todos',1,'listagem de todos os agendamentos.','agendamento_index','ativo','alarm-fill',NULL,1);
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
  `id_grupÃo` int NOT NULL,
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
INSERT INTO `planos` VALUES (1,'Plano Full Administrador','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\",\"hospedagemDeC\\u00e3es\",\"cl\\u00ednicaVeterin\\u00e1ria\"]',0.00,'Inativo',0,NULL,'2025-04-29 22:58:35','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\",\"hospedagemDeC\\u00e3es\",\"cl\\u00ednicaVeterin\\u00e1ria\"]'),(2,'Plano básico','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\"]',64.90,'Ativo',0,NULL,'2025-05-03 10:17:47','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\"]'),(3,'Plano intermediário','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\",\"hospedagemDeC\\u00e3es\"]',85.90,'Ativo',1,NULL,'2025-05-03 10:18:03','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\",\"hospedagemDeC\\u00e3es\"]'),(4,'Plano Avançado','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\",\"hospedagemDeC\\u00e3es\",\"cl\\u00ednicaVeterin\\u00e1ria\"]',99.90,'Ativo',0,NULL,'2025-05-03 10:18:19','[\"agendamentosDePets\",\"cadastroDeClientes\",\"cadastroDePets\",\"servi\\u00e7osDoPetshop\",\"\\u00e1reaDeFinanceiro\",\"gest\\u00e3oDeUsu\\u00e1rios\",\"banhoETosa\",\"hospedagemDeC\\u00e3es\",\"cl\\u00ednicaVeterin\\u00e1ria\"]');
/*!40000 ALTER TABLE `planos` ENABLE KEYS */;
UNLOCK TABLES;
