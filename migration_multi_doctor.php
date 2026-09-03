<?php
// migration_multi_doctor.php - Migration to support multiple doctors in channeling centers
require_once __DIR__ . '/config/db.php';

try {
    // 1. Create doctors table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `doctors` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `qualifications` VARCHAR(255),
        `slmc_reg` VARCHAR(100),
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Seed first doctor from settings if table empty
    $docCount = $pdo->query("SELECT COUNT(*) FROM `doctors`")->fetchColumn();
    if ($docCount == 0) {
        $sett = $pdo->query("SELECT * FROM `settings` LIMIT 1")->fetch();
        if ($sett) {
            $stmt = $pdo->prepare("INSERT INTO `doctors` (`id`, `name`, `qualifications`, `slmc_reg`) VALUES (1, ?, ?, ?)");
            $stmt->execute([
                $sett['doctor_name'] ?: 'Dr. Sampath Perera',
                $sett['qualifications'] ?: 'MBBS (Colombo), MD (Family Medicine)',
                $sett['slmc_reg'] ?: 'SLMC 34821'
            ]);
            echo "Seeded default doctor into doctors table.\n";
        } else {
            $pdo->exec("INSERT INTO `doctors` (`id`, `name`, `qualifications`, `slmc_reg`) VALUES (1, 'Dr. Sampath Perera', 'MBBS (Colombo), MD (Family Medicine)', 'SLMC 34821')");
            echo "Seeded initial fallback doctor into doctors table.\n";
        }
    }

    // 3. Add active_doctor_id to settings if not exists
    $colsSettings = $pdo->query("SHOW COLUMNS FROM `settings` LIKE 'active_doctor_id'")->fetch();
    if (!$colsSettings) {
        $pdo->exec("ALTER TABLE `settings` ADD COLUMN `active_doctor_id` INT DEFAULT 1;");
        echo "Added active_doctor_id to settings table.\n";
    }

    // 4. Add doctor_id to prescriptions if not exists
    $colsPrescriptions = $pdo->query("SHOW COLUMNS FROM `prescriptions` LIKE 'doctor_id'")->fetch();
    if (!$colsPrescriptions) {
        $pdo->exec("ALTER TABLE `prescriptions` ADD COLUMN `doctor_id` INT DEFAULT 1;");
        echo "Added doctor_id to prescriptions table.\n";
    }

    echo "Migration completed successfully!\n";
} catch (\Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
