<?php
// config/db.php - PDO Database Connection & Auto Schema Initializer with Dispensary Billing

$host = '127.0.0.1';
$port = '3306';
$user = 'root';
$pass = ''; // Default XAMPP password is empty
$dbname = 'prescription_db';
$charset = 'utf8mb4';

// Check if portable mysql folder is present and default to port 3307
if (is_dir(__DIR__ . '/../bin/mysql')) {
    $port = '3307';
}

// Dynamic Database Config for Multi-Device Network Setup
$userDataPath = getenv('USER_DATA_PATH');
if ($userDataPath) {
    // In Electron Desktop Mode, store config in user's AppData directory so it survives updates!
    $configPath = $userDataPath . '/db_config.json';
    
    // If config doesn't exist in AppData yet, migrate/copy it from local installation folder if present
    $localConfigPath = __DIR__ . '/db_config.json';
    if (!file_exists($configPath) && file_exists($localConfigPath)) {
        @copy($localConfigPath, $configPath);
    }
} else {
    // Fallback for standalone XAMPP / Dev mode
    $configPath = __DIR__ . '/db_config.json';
}

// Check environment variable overrides first (SEC-04)
$host = getenv('DB_HOST') ?: $host;
$port = getenv('DB_PORT') ?: $port;
$user = getenv('DB_USER') ?: $user;
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : $pass;
$dbname = getenv('DB_NAME') ?: $dbname;

if (file_exists($configPath)) {
    @chmod($configPath, 0600);
    $config = json_decode(@file_get_contents($configPath), true);
    if ($config && !getenv('DB_HOST')) {
        $host = $config['host'] ?? $host;
        $port = $config['port'] ?? $port;
        $user = $config['user'] ?? $user;
        $pass = $config['pass'] ?? $pass;
        $dbname = $config['dbname'] ?? $dbname;
    }
}

$db_connected = false;
$db_error_message = '';
$pdo = null;

try {
    $pdo_init = new PDO("mysql:host=$host;port=$port;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 3
    ]);
    
    $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo_init = null;

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Decouple schema DDL initialization from every request lifecycle for high performance (Issue #9)
    $schemaFlag = ($userDataPath ?: __DIR__) . '/.schema_initialized';
    if (!file_exists($schemaFlag)) {
        init_tables($pdo);
        @file_put_contents($schemaFlag, date('Y-m-d H:i:s'));
    }
    $db_connected = true;

} catch (\PDOException $e) {
    $db_connected = false;
    $db_error_message = $e->getMessage();
}

function init_tables($pdo) {
    // Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `doctor_name` VARCHAR(255) NOT NULL DEFAULT 'Dr. Sampath Perera',
        `qualifications` VARCHAR(255) NOT NULL DEFAULT 'MBBS (Colombo), MD',
        `slmc_reg` VARCHAR(100) NOT NULL DEFAULT 'SLMC-34821',
        `clinic_name` VARCHAR(255) NOT NULL DEFAULT 'CureCare Medical Center',
        `clinic_address` VARCHAR(255) NOT NULL DEFAULT 'No. 124, High Level Road, Maharagama',
        `clinic_phone` VARCHAR(100) NOT NULL DEFAULT '011-2849102 / 077-1234567',
        `print_paper_size` VARCHAR(50) NOT NULL DEFAULT 'A5',
        `default_language` VARCHAR(50) NOT NULL DEFAULT 'bilingual',
        `subscription_status` VARCHAR(50) NOT NULL DEFAULT 'active',
        `subscription_plan` VARCHAR(100) NOT NULL DEFAULT 'Monthly Pro (LKR 3,000/mo)',
        `subscription_expires_at` DATE DEFAULT '2026-12-31',
        `license_key` VARCHAR(100) NOT NULL DEFAULT 'CRX-8829-PRO-MONTHLY',
        `practice_mode` VARCHAR(50) NOT NULL DEFAULT 'dispensary',
        `enable_dispensary_billing` TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Replace hardcoded settings seed
    $seedFile = __DIR__ . '/../seeds/default_clinic.json';
    if (file_exists($seedFile)) {
        $seed = json_decode(file_get_contents($seedFile), true) ?? [];
        $pdo->prepare("INSERT IGNORE INTO settings
            (id, clinic_name, doctor_name, address, phone, slmc_reg)
            VALUES (1, :clinic_name, :doctor_name, :address, :phone, :slmc_reg)")
            ->execute($seed);
    }

    // Patients Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `patients` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `patient_code` VARCHAR(50) UNIQUE NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `age` INT NOT NULL,
        `gender` ENUM('Male', 'Female', 'Other') NOT NULL DEFAULT 'Male',
        `phone` VARCHAR(50),
        `address` VARCHAR(255),
        `allergies` TEXT,
        `blood_group` VARCHAR(10),
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Medicines Master Database Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `medicines` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `generic_name` VARCHAR(255),
        `type` VARCHAR(50) DEFAULT 'Tablet',
        `default_strength` VARCHAR(50),
        `default_dosage` VARCHAR(50),
        `default_instruction` VARCHAR(100),
        `unit_price` DECIMAL(10,2) DEFAULT 10.00,
        `stock_qty` INT DEFAULT 500,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Full-Text & Search Performance Indexes (Issue #11)
    try { $pdo->exec("CREATE INDEX `idx_patient_code` ON `patients` (`patient_code`);"); } catch (\PDOException $e) {}
    try { $pdo->exec("CREATE INDEX `idx_patient_phone` ON `patients` (`phone`);"); } catch (\PDOException $e) {}
    try { $pdo->exec("CREATE FULLTEXT INDEX `idx_patient_search` ON `patients` (`name`, `phone`, `patient_code`);"); } catch (\PDOException $e) {}
    try { $pdo->exec("CREATE FULLTEXT INDEX `idx_medicine_search` ON `medicines` (`name`, `generic_name`);"); } catch (\PDOException $e) {}

    // Replace hardcoded medicines seed
    $medFile = __DIR__ . '/../seeds/default_medicines.json';
    if (file_exists($medFile)) {
        $meds = json_decode(file_get_contents($medFile), true) ?? [];
        $stmt = $pdo->prepare("INSERT IGNORE INTO medicines
            (name, strength, unit, unit_price, stock_qty, instruction_en, instruction_si)
            VALUES (:name, :strength, :unit, :unit_price, :stock_qty, :instruction_en, :instruction_si)");
        foreach ($meds as $med) {
            $stmt->execute($med);
        }
    }

    // Templates Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Template Items Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `template_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `template_id` INT NOT NULL,
        `medicine_name` VARCHAR(255) NOT NULL,
        `strength` VARCHAR(50),
        `dosage` VARCHAR(100),
        `instruction` VARCHAR(150),
        `duration` VARCHAR(50),
        `quantity` VARCHAR(50),
        FOREIGN KEY (`template_id`) REFERENCES `templates`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Seed default template if empty
    $tpl_count = $pdo->query("SELECT COUNT(*) FROM `templates`")->fetchColumn();
    if ($tpl_count == 0) {
        $pdo->exec("INSERT INTO `templates` (`name`, `description`) VALUES 
        ('Fever & Cold (උණ සහ හෙම්බිරිස්සාව)', 'Standard prescription package for common viral flu / fever'),
        ('Gastritis (ගැස්ට්‍රයිටිස්)', 'Standard treatment for acute hyperacidity & gastritis');");

        $tpl1_id = $pdo->lastInsertId();
        $pdo->exec("INSERT INTO `template_items` (`template_id`, `medicine_name`, `strength`, `dosage`, `instruction`, `duration`, `quantity`) VALUES
        ($tpl1_id, 'Paracetamol', '500mg', '2 Tab tds (1-0-1)', 'After Meals / කෑමට පසු', '3 Days', '18 Tabs'),
        ($tpl1_id, 'Cetirizine', '10mg', '1 Tab nocte (0-0-1)', 'At Night / රාත්‍රියට', '5 Days', '5 Tabs');");
    }

    // Prescriptions Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `prescriptions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `rx_number` VARCHAR(100) UNIQUE NOT NULL,
        `patient_id` INT NOT NULL,
        `bp` VARCHAR(50),
        `pulse` VARCHAR(50),
        `weight` VARCHAR(50),
        `temp` VARCHAR(50),
        `chief_complaint` TEXT,
        `diagnosis` TEXT,
        `advice` TEXT,
        `follow_up_date` DATE,
        `consultation_fee` DECIMAL(10,2) DEFAULT 1000.00,
        `medicines_total` DECIMAL(10,2) DEFAULT 0.00,
        `discount` DECIMAL(10,2) DEFAULT 0.00,
        `grand_total` DECIMAL(10,2) DEFAULT 0.00,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Prescription Items Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `prescription_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `prescription_id` INT NOT NULL,
        `medicine_name` VARCHAR(255) NOT NULL,
        `strength` VARCHAR(50),
        `dosage` VARCHAR(100),
        `instruction` VARCHAR(150),
        `duration` VARCHAR(50),
        `quantity` VARCHAR(50),
        `unit_price` DECIMAL(10,2) DEFAULT 0.00,
        `item_total` DECIMAL(10,2) DEFAULT 0.00,
        `item_source` VARCHAR(50) NOT NULL DEFAULT 'clinic',
        FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Multi-doctor setup
    $pdo->exec("CREATE TABLE IF NOT EXISTS `doctors` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `qualifications` VARCHAR(255),
        `slmc_reg` VARCHAR(100),
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $docCount = $pdo->query("SELECT COUNT(*) FROM `doctors`")->fetchColumn();
    if ($docCount == 0) {
        $pdo->exec("INSERT INTO `doctors` (`id`, `name`, `qualifications`, `slmc_reg`) VALUES (1, 'Dr. Sampath Perera', 'MBBS (Colombo), MD (Family Medicine)', 'SLMC 34821')");
    }

    // Waiting queue table
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

    // Channeling bills table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `channeling_bills` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `bill_number` VARCHAR(100) UNIQUE NOT NULL,
        `patient_id` INT NOT NULL,
        `doctor_id` INT NOT NULL,
        `token_number` INT NOT NULL,
        `doctor_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `hospital_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `payment_status` ENUM('Paid', 'Unpaid') DEFAULT 'Paid',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add database indexes for high-speed autocomplete search performance
    try { $pdo->exec("CREATE INDEX `idx_patients_name` ON `patients` (`name`);"); } catch (\Exception $e) {}
    try { $pdo->exec("CREATE INDEX `idx_patients_phone` ON `patients` (`phone`);"); } catch (\Exception $e) {}
    try { $pdo->exec("CREATE INDEX `idx_patients_code` ON `patients` (`patient_code`);"); } catch (\Exception $e) {}
    try { $pdo->exec("CREATE INDEX `idx_prescriptions_patient` ON `prescriptions` (`patient_id`);"); } catch (\Exception $e) {}
    try { $pdo->exec("CREATE INDEX `idx_prescription_items_rx` ON `prescription_items` (`prescription_id`);"); } catch (\Exception $e) {}
}
