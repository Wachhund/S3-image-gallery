-- S3 Image Gallery - Database Schema
-- Engine: InnoDB, Charset: utf8mb4

CREATE TABLE IF NOT EXISTS `dirs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dirname` VARCHAR(500) NOT NULL,
    `bucket` VARCHAR(255) NOT NULL,
    `parent_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dirname` (`dirname`(255)),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_bucket` (`bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(500) NOT NULL,
    `time` INT UNSIGNED NOT NULL DEFAULT 0,
    `size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `hash` VARCHAR(64) NOT NULL DEFAULT '',
    `dir_id` INT UNSIGNED NOT NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_name` (`name`(255)),
    KEY `idx_dir_id` (`dir_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `thumbs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(500) NOT NULL,
    `image_id` INT UNSIGNED NOT NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_image_id` (`image_id`),
    UNIQUE KEY `uq_image_id` (`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `passkeys` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `credential_id` VARBINARY(1024) NOT NULL,
    `public_key` BLOB NOT NULL,
    `counter` INT UNSIGNED NOT NULL DEFAULT 0,
    `transports` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_credential_id` (`credential_id`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `otp_status` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `consumed` TINYINT(1) NOT NULL DEFAULT 0,
    `consumed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: OTP is initially not consumed
INSERT INTO `otp_status` (`consumed`) VALUES (0);
