ALTER TABLE `homepet`.`usuario`
    ADD COLUMN `access_level` ENUM("Admin", "Atendente") NULL DEFAULT NULL AFTER `roles`;