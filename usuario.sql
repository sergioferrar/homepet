/*
 Navicat Premium Data Transfer

 Source Server         : Local - Ubuntu
 Source Server Type    : MySQL
 Source Server Version : 80042
 Source Host           : 127.0.0.1:3306
 Source Schema         : homepet_login

 Target Server Type    : MySQL
 Target Server Version : 80042
 File Encoding         : 65001

 Date: 30/06/2025 21:42:22
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for usuario
-- ----------------------------
DROP TABLE IF EXISTS `usuario`;
CREATE TABLE `usuario`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome_usuario` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `senha` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `roles` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '[\"ROLE_ADMIN\"]',
  `access_level` enum('Super Admin','Admin','Atendente','Balconista') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `petshop_id` bigint NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `nome_usuario`(`nome_usuario`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of usuario
-- ----------------------------
INSERT INTO `usuario` VALUES (1, 'Sergio Ferrari', '$2y$13$G2X.5OCBB90f6hPzhqJGp.gIaxkMfBRfpBDULIEUvJtU612EWj9Ny', 'sergioferrari150395@gmail.com', '[\"ROLE_ADMIN\"]', 'Super Admin', 1);

SET FOREIGN_KEY_CHECKS = 1;
