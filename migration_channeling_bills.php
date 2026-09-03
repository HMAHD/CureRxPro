<?php
// migration_channeling_bills.php - Create channeling_bills table
require_once __DIR__ . '/config/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `channeling_bills` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `bill_number` VARCHAR(100) UNIQUE NOT NULL,
        `patient_id` INT NOT NULL,
        `doctor_id` INT NOT NULL,
        `token_number` INT NOT NULL,
        `doctor_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `hospital_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Channeling Bills table created successfully!\n";
} catch (\Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
