<?php
// migration_waiting_queue.php - Create waiting queue table for clinics
require_once __DIR__ . '/config/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `waiting_queue` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `patient_id` INT NOT NULL,
        `doctor_id` INT NOT NULL,
        `token_number` INT NOT NULL,
        `status` ENUM('Waiting', 'Serving', 'Completed', 'Cancelled') DEFAULT 'Waiting',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Waiting Queue table created successfully!\n";
} catch (\Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
