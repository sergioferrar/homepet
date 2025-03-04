ALTER TABLE `homepet`.`usuario`
    ADD COLUMN `access_level` enum('Super Admin','Admin','Atendente') NULL DEFAULT NULL AFTER `roles`,
    ADD COLUMN `petshop_id` bigint(20) NOT NULL AFTER `access_level`;
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `homepet_login`.`estabelecimento`
(
    `id`              INT NOT NULL AUTO_INCREMENT,
    `razaoSocial`     VARCHAR(300) NULL,
    `cnpj`            VARCHAR(25) NULL,
    `rua`             VARCHAR(255) NULL,
    `numero`          VARCHAR(45) NULL,
    `complemento`     VARCHAR(200) NULL,
    `bairro`          VARCHAR(255) NULL,
    `cidade`          VARCHAR(255) NULL,
    `pais`            VARCHAR(200) NULL,
    `cep`             INT NULL,
    `status`          ENUM("Ativo", "Suspenso", "Inativo") NOT NULL DEFAULT 'Inativo',
    `dataCadastro`    TIMESTAMP NULL,
    `dataAtualizacao` TIMESTAMP NULL,
    PRIMARY KEY (`id`)
);
