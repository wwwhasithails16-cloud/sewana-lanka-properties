USE `sewana_lanka_db`;

-- Import this file in phpMyAdmin when adding phone OTP reset to an existing site.
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) NULL AFTER `password`;

UPDATE `users`
SET `phone` = '0751227606'
WHERE `username` = 'admin';

CREATE UNIQUE INDEX IF NOT EXISTS `idx_users_phone`
    ON `users` (`phone`);

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
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;
