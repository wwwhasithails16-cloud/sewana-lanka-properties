USE `sewana_lanka_db`;

-- Import this file in phpMyAdmin when adding password reset to an existing site.
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `email` VARCHAR(190) NULL AFTER `username`;

UPDATE `users`
SET `email` = 'hasithails16@gmail.com'
WHERE `username` = 'admin'
  AND (`email` IS NULL OR `email` = '');

CREATE UNIQUE INDEX IF NOT EXISTS `idx_users_email`
    ON `users` (`email`);

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `request_ip` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_password_reset_user` (`user_id`),
    INDEX `idx_password_reset_expiry` (`expires_at`),
    CONSTRAINT `fk_password_reset_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;
