USE `sewana_lanka_db`;

INSERT INTO `users` (`username`, `password`)
VALUES (
    'admin',
    '$2y$12$atx2WBQfTWzFG8h1E5d4YOXpnJVGjfJh3DB8KmN0Oi2xeb1QEfuIK'
)
ON DUPLICATE KEY UPDATE
    `password` = VALUES(`password`);
