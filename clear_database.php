<?php
require_once __DIR__ . '/config/db.php';

try {
    // Disable foreign key checks for clean truncate
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Clear test transaction data and old medicines catalogue
    $pdo->exec("TRUNCATE TABLE `prescription_items`;");
    $pdo->exec("TRUNCATE TABLE `prescriptions`;");
    $pdo->exec("TRUNCATE TABLE `medicines`;");
    $pdo->exec("TRUNCATE TABLE `patients`;");

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "Database successfully cleared (medicines, prescriptions, prescription_items, patients truncated). Settings preserved.\n";
} catch (\Exception $e) {
    echo "Error clearing database: " . $e->getMessage() . "\n";
}
