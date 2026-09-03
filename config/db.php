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

    init_tables($pdo);
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

    // Check if settings row exists, insert default if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM `settings`");
    if ($stmt->fetchColumn() == 0) {
        $expiry = date('Y-m-d', strtotime('+30 days'));
        $pdo->exec("INSERT INTO `settings` (`doctor_name`, `qualifications`, `slmc_reg`, `clinic_name`, `clinic_address`, `clinic_phone`, `print_paper_size`, `default_language`, `subscription_expires_at`) 
        VALUES ('Dr. Sampath Perera', 'MBBS (Colombo), MD (Family Medicine)', 'SLMC 34821', 'CureCare Medical Center', 'No. 124, High Level Road, Maharagama', '011-2849102 / 077-1234567', 'A5', 'bilingual', '$expiry');");
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

    // Check if medicines table is empty, insert default Sri Lankan medicine templates if empty
    $medCount = $pdo->query("SELECT COUNT(*) FROM `medicines`")->fetchColumn();
    if ($medCount == 0) {
        $defaultMeds = [
            ['Paracetamol', 'Paracetamol', 'Tablet', '500mg', '1-1-1-1', 'කෑමට පසු (After meals)', 1.50, 1000],
            ['Amoxicillin', 'Amoxicillin', 'Capsule', '500mg', '1-1-1-0', 'කෑමට පසු (After meals)', 8.50, 500],
            ['Cetirizine', 'Cetirizine', 'Tablet', '10mg', '0-0-1-0', 'රාත්‍රියට කෑමට පසු (Night, after meals)', 2.00, 1000],
            ['Metformin', 'Metformin', 'Tablet', '500mg', '1-0-1-0', 'කෑම සමඟ (With meals)', 3.50, 1000],
            ['Atorvastatin', 'Atorvastatin', 'Tablet', '20mg', '0-0-1-0', 'රාත්‍රියට කෑමට පසු (Night, after meals)', 12.00, 500],
            ['Omeprazole', 'Omeprazole', 'Capsule', '20mg', '1-0-1-0', 'කෑමට පැය භාගයකට පෙර (30 mins before meals)', 4.00, 500],
            ['Domperidone', 'Domperidone', 'Tablet', '10mg', '1-0-1-0', 'කෑමට පැය භාගයකට පෙර (30 mins before meals)', 3.00, 500],
            ['Salbutamol', 'Salbutamol', 'Tablet', '2mg', '1-1-1-0', 'කෑමට පසු (After meals)', 1.00, 1000],
            ['Panadol', 'Paracetamol', 'Tablet', '500mg', '2-0-2-0', 'උණ හෝ කැක්කුම ඇති විට (When needed)', 2.50, 1000],
            ['Peditral Sachet', 'Oral Rehydration Salts', 'Sachet', '1 Sachet', '1-0-1-0', 'ජලය ලීටරයක දියකර පානය කරන්න (Dissolve in 1L water)', 25.00, 100],
            ['Clopidogrel', 'Clopidogrel', 'Tablet', '75mg', '0-1-0-0', 'කෑමට පසු (After meals)', 18.00, 300],
            ['Losartan Potassium', 'Losartan Potassium', 'Tablet', '50mg', '1-0-0-0', 'උදෑසන කෑමට පසු (Morning, after meals)', 8.00, 500],
            ['Erythromycin', 'Erythromycin', 'Tablet', '250mg', '1-1-1-1', 'කෑමට පැය බාගයකට පෙර (Before meals)', 9.00, 500],
            ['Cloxacillin', 'Cloxacillin', 'Capsule', '250mg', '1-1-1-1', 'කෑමට පැය බාගයකට පෙර (Before meals)', 7.50, 500],
            ['Fusi-C Cream', 'Fusidic Acid', 'Ointment/Cream', '15g', 'Apply 2 times daily', 'බාහිර භාවිතය සඳහා පමණි (For external use only)', 280.00, 50]
        ];

        $insStmt = $pdo->prepare("INSERT INTO `medicines` (`name`, `generic_name`, `type`, `default_strength`, `default_dosage`, `default_instruction`, `unit_price`, `stock_qty`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($defaultMeds as $med) {
            $insStmt->execute($med);
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

    // Alter table fallback checks to ensure columns exist for existing tables
    try { $pdo->exec("ALTER TABLE `settings` ADD COLUMN `practice_mode` VARCHAR(50) NOT NULL DEFAULT 'dispensary';"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `settings` ADD COLUMN `subscription_status` VARCHAR(50) NOT NULL DEFAULT 'active';"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `settings` ADD COLUMN `subscription_plan` VARCHAR(100) NOT NULL DEFAULT 'Monthly Pro (LKR 3,000/mo)';"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `settings` ADD COLUMN `subscription_expires_at` DATE DEFAULT '2026-12-31';"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `settings` ADD COLUMN `license_key` VARCHAR(100) NOT NULL DEFAULT 'CRX-8829-PRO-MONTHLY';"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `medicines` ADD COLUMN `unit_price` DECIMAL(10,2) DEFAULT 10.00;"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `medicines` ADD COLUMN `stock_qty` INT DEFAULT 500;"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `prescription_items` ADD COLUMN `unit_price` DECIMAL(10,2) DEFAULT 0.00;"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `prescription_items` ADD COLUMN `item_total` DECIMAL(10,2) DEFAULT 0.00;"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `prescription_items` ADD COLUMN `item_source` VARCHAR(50) NOT NULL DEFAULT 'clinic';"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `prescriptions` ADD COLUMN `consultation_fee` DECIMAL(10,2) DEFAULT 1000.00;"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `prescriptions` ADD COLUMN `medicines_total` DECIMAL(10,2) DEFAULT 0.00;"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `prescriptions` ADD COLUMN `discount` DECIMAL(10,2) DEFAULT 0.00;"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `prescriptions` ADD COLUMN `grand_total` DECIMAL(10,2) DEFAULT 0.00;"); } catch (\Exception $e) {}

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

    try { $pdo->exec("ALTER TABLE `settings` ADD COLUMN `active_doctor_id` INT DEFAULT 1;"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `prescriptions` ADD COLUMN `doctor_id` INT DEFAULT 1;"); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE `prescriptions` ADD COLUMN `hospital_fee` DECIMAL(10,2) DEFAULT 0.00;"); } catch (\Exception $e) {}

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

    try { $pdo->exec("ALTER TABLE `channeling_bills` ADD COLUMN `payment_status` ENUM('Paid', 'Unpaid') DEFAULT 'Paid';"); } catch (\Exception $e) {}

    // Add database indexes for high-speed autocomplete search performance
    try { $pdo->exec("CREATE INDEX `idx_patients_name` ON `patients` (`name`);"); } catch (\Exception $e) {}
    try { $pdo->exec("CREATE INDEX `idx_patients_phone` ON `patients` (`phone`);"); } catch (\Exception $e) {}
    try { $pdo->exec("CREATE INDEX `idx_patients_code` ON `patients` (`patient_code`);"); } catch (\Exception $e) {}
    try { $pdo->exec("CREATE INDEX `idx_prescriptions_patient` ON `prescriptions` (`patient_id`);"); } catch (\Exception $e) {}
    try { $pdo->exec("CREATE INDEX `idx_prescription_items_rx` ON `prescription_items` (`prescription_id`);"); } catch (\Exception $e) {}
}
