USE `sewana_lanka_db`;

-- Run this file once when upgrading the older Sewana Lanka database.
ALTER TABLE `properties`
    ADD COLUMN IF NOT EXISTS `district` VARCHAR(80) NOT NULL DEFAULT 'Colombo' AFTER `price`,
    ADD COLUMN IF NOT EXISTS `status` ENUM('available', 'sold') NOT NULL DEFAULT 'available' AFTER `district`,
    ADD COLUMN IF NOT EXISTS `price_amount` DECIMAL(15,2) NULL AFTER `price`,
    ADD COLUMN IF NOT EXISTS `property_type` ENUM('house', 'land', 'commercial', 'apartment') NOT NULL DEFAULT 'house' AFTER `price_amount`;

-- Convert existing values such as "Rs. 25,000,000" into a searchable number.
UPDATE `properties`
SET `price_amount` = CAST(
    REPLACE(REPLACE(REPLACE(REPLACE(LOWER(`price`), 'rs.', ''), 'rs', ''), ',', ''), ' ', '')
    AS DECIMAL(15,2)
)
WHERE `price_amount` IS NULL;

CREATE INDEX IF NOT EXISTS `idx_properties_district` ON `properties` (`district`);
CREATE INDEX IF NOT EXISTS `idx_properties_status` ON `properties` (`status`);
CREATE INDEX IF NOT EXISTS `idx_properties_type_price` ON `properties` (`property_type`, `price_amount`);

-- Add the administrator recovery identity when upgrading an existing site.
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) NULL AFTER `password`;

UPDATE `users` SET `phone` = '0751227606' WHERE `username` = 'admin';

CREATE UNIQUE INDEX IF NOT EXISTS `idx_users_phone` ON `users` (`phone`);

-- Each permanent-OTP use still requires a short-lived, rate-limited request.
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

-- Keep each old cover image as the first gallery image.
INSERT INTO `property_media` (`property_id`, `file_name`, `media_type`, `sort_order`)
SELECT p.id, p.image, 'image', 1
FROM `properties` p
WHERE p.image IS NOT NULL
  AND p.image <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM `property_media` pm
      WHERE pm.property_id = p.id
        AND pm.file_name = p.image
  );

-- Reset administrator credentials:
-- Username: admin
-- Password: DarkForest@123
INSERT INTO `users` (`username`, `password`)
VALUES (
    'admin',
    '$2y$12$atx2WBQfTWzFG8h1E5d4YOXpnJVGjfJh3DB8KmN0Oi2xeb1QEfuIK'
)
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`);
