<?php
$host = 'localhost';
$dbName = 'sewana_lanka_db';
$username = 'root';
$password = '';

try {
    $conn = new PDO(
        "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // API callers must receive JSON so the website can show a useful message.
    if (str_contains(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/api/')) {
        throw new RuntimeException('Database connection failed.', 0, $e);
    }
    exit('Database Connection Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

/**
 * Keep an older Sewana Lanka database compatible with the current admin form.
 * This is intentionally safe to run on every request: changes are made only
 * when a required column/table does not already exist.
 */
function ensureSewanaDatabase(PDO $conn, string $dbName): void
{
    $columnQuery = $conn->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :database_name AND TABLE_NAME = :table_name'
    );
    $columnQuery->execute([
        'database_name' => $dbName,
        'table_name' => 'properties',
    ]);
    $propertyColumns = array_flip($columnQuery->fetchAll(PDO::FETCH_COLUMN));

    $requiredColumns = [
        'price_amount' => "DECIMAL(15,2) NULL AFTER `price`",
        'property_type' => "ENUM('house','land','commercial','apartment') NOT NULL DEFAULT 'house' AFTER `price_amount`",
        'district' => "VARCHAR(80) NOT NULL DEFAULT 'Colombo' AFTER `property_type`",
        'status' => "ENUM('available','sold') NOT NULL DEFAULT 'available' AFTER `district`",
    ];

    foreach ($requiredColumns as $name => $definition) {
        if (!isset($propertyColumns[$name])) {
            // Column names and definitions come only from the fixed map above.
            $conn->exec("ALTER TABLE `properties` ADD COLUMN `{$name}` {$definition}");
        }
    }

    // Older records have a formatted price string but no numeric price. Fill
    // it once so price indexes and range comparisons work for those records.
    $conn->exec(
        "UPDATE `properties`
         SET `price_amount` = CAST(
             REPLACE(REPLACE(REPLACE(REPLACE(LOWER(`price`), 'rs.', ''), 'rs', ''), ',', ''), ' ', '')
             AS DECIMAL(15,2)
         )
         WHERE `price_amount` IS NULL"
    );

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `property_media` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `property_id` INT NOT NULL,
            `file_name` VARCHAR(255) NOT NULL,
            `media_type` ENUM('image', 'video') NOT NULL,
            `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_property_media_property` (`property_id`),
            CONSTRAINT `fk_property_media_property`
                FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB"
    );
}

try {
    ensureSewanaDatabase($conn, $dbName);
} catch (Throwable $e) {
    // Show an actionable message instead of failing later with an SQL error
    // when the administrator presses Save & Publish.
    exit(
        'Database update error. Import update_database.sql in phpMyAdmin, then reload this page. Details: '
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
    );
}
