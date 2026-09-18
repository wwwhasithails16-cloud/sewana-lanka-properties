CREATE DATABASE IF NOT EXISTS `sewana_lanka_db`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `sewana_lanka_db`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    -- Used only to confirm the configured administrator recovery account.
    `phone` VARCHAR(20) NULL UNIQUE
) ENGINE=InnoDB;

-- Short-lived reset requests protect use of the permanent recovery OTP with
-- expiry, request tokens, attempt limits and an audit timestamp.
CREATE TABLE IF NOT EXISTS `password_reset_otps` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `request_token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    `otp_hash` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `request_ip` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_password_reset_otp_user` (`user_id`),
    INDEX `idx_password_reset_otp_expiry` (`expires_at`),
    CONSTRAINT `fk_password_reset_otp_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `properties` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `price` VARCHAR(100) NOT NULL,
    `price_amount` DECIMAL(15,2) NULL,
    `property_type` ENUM('house', 'land', 'commercial', 'apartment') NOT NULL DEFAULT 'house',
    `district` VARCHAR(80) NOT NULL,
    `status` ENUM('available', 'sold') NOT NULL DEFAULT 'available',
    `image` VARCHAR(255) NOT NULL,
    `details` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_properties_district` (`district`),
    INDEX `idx_properties_type_price` (`property_type`, `price_amount`),
    INDEX `idx_properties_status` (`status`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `property_media` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `property_id` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `media_type` ENUM('image', 'video') NOT NULL,
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_property_media_property` (`property_id`),
    CONSTRAINT `fk_property_media_property`
        FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- Default administrator:
-- Username: admin
-- Password: DarkForest@123
INSERT INTO `users` (`username`, `password`, `phone`)
VALUES (
    'admin',
    '$2y$12$atx2WBQfTWzFG8h1E5d4YOXpnJVGjfJh3DB8KmN0Oi2xeb1QEfuIK',
    '0751227606'
)
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `phone` = VALUES(`phone`);
