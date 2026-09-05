<?php
// api/backup.php - 1-Click Complete Database Backup & Safety Restore API
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';
verify_api_request();

function backup_encryption_key(): string {
    $secretFile = (getenv('USER_DATA_PATH') ?: __DIR__ . '/..') . '/api_secret.key';
    $secret = file_exists($secretFile) ? trim(file_get_contents($secretFile)) : 'default-insecure';
    return hash('sha256', $secret, true); // 32 raw bytes for AES-256
}

function encrypt_backup(string $plaintext): string {
    $key = backup_encryption_key();
    $iv  = random_bytes(16);
    $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

function decrypt_backup(string $ciphertext): string|false {
    $key  = backup_encryption_key();
    $raw  = base64_decode($ciphertext);
    if (strlen($raw) < 17) return false;
    $iv     = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    return openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}

function fetch_table_rows(\PDO $pdo, string $table): array {
    $stmt = $pdo->query("SELECT * FROM `{$table}`");
    $rows = [];
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

function generate_full_backup(\PDO $pdo): array {
    return [
        'version'            => '2.5.0',
        'exported_at'        => date('Y-m-d H:i:s'),
        'settings'           => fetch_table_rows($pdo, 'settings'),
        'doctors'            => fetch_table_rows($pdo, 'doctors'),
        'patients'           => fetch_table_rows($pdo, 'patients'),
        'medicines'          => fetch_table_rows($pdo, 'medicines'),
        'templates'          => fetch_table_rows($pdo, 'templates'),
        'template_items'     => fetch_table_rows($pdo, 'template_items'),
        'prescriptions'      => fetch_table_rows($pdo, 'prescriptions'),
        'prescription_items' => fetch_table_rows($pdo, 'prescription_items'),
        'waiting_queue'      => fetch_table_rows($pdo, 'waiting_queue'),
        'channeling_bills'   => fetch_table_rows($pdo, 'channeling_bills'),
    ];
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'export') {
    try {
        $backup = generate_full_backup($pdo);
        $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $encrypted = encrypt_backup($json);
        $filename = 'CureRx_Pro_Backup_' . date('Y-m-d_His') . '.crxbak';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $encrypted;
        exit;
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Export failed: ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'import') {
    try {
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Please select a valid backup JSON file.']);
            exit;
        }

        $raw = file_get_contents($_FILES['backup_file']['tmp_name']);
        // Support both legacy .json and new encrypted .crxbak format
        $decoded = json_decode($raw, true);
        if ($decoded === null) {
            $plaintext = decrypt_backup($raw);
            if ($plaintext === false) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Cannot decrypt backup file. Wrong installation key?']);
                exit;
            }
            $decoded = json_decode($plaintext, true);
        }
        $data = $decoded;

        if (!$data || !is_array($data) || (!isset($data['medicines']) && !isset($data['patients']))) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid backup file format. Essential database structure missing.']);
            exit;
        }

        // --- SAFETY SNAPSHOT FEATURE (SEC-02) ---
        // Generate an automatic pre-restore safety backup file before truncating tables
        $userDataPath = getenv('USER_DATA_PATH');
        $backupDir = $userDataPath ? $userDataPath . '/backups' : __DIR__ . '/../backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $safetySnapshot = generate_full_backup($pdo);
        $safetyFilename = $backupDir . '/pre_restore_safety_' . date('Ymd_His') . '.json';
        $saved = @file_put_contents($safetyFilename, json_encode($safetySnapshot, JSON_PRETTY_PRINT));
        
        if ($saved === false) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create safety pre-restore backup snapshot. Restore aborted for security.']);
            exit;
        }

        $pdo->beginTransaction();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

        // DELETE instead of TRUNCATE — TRUNCATE causes an implicit commit in MySQL,
        // which would prevent rollback if the insert phase fails.
        $pdo->exec("DELETE FROM `channeling_bills`;");
        $pdo->exec("DELETE FROM `waiting_queue`;");
        $pdo->exec("DELETE FROM `prescription_items`;");
        $pdo->exec("DELETE FROM `prescriptions`;");
        $pdo->exec("DELETE FROM `template_items`;");
        $pdo->exec("DELETE FROM `templates`;");
        $pdo->exec("DELETE FROM `medicines`;");
        $pdo->exec("DELETE FROM `patients`;");
        $pdo->exec("DELETE FROM `doctors`;");

        // Restore Doctors
        if (!empty($data['doctors'])) {
            $stmt = $pdo->prepare("INSERT INTO `doctors` (`id`, `name`, `qualifications`, `slmc_reg`, `created_at`) VALUES (?, ?, ?, ?, ?)");
            foreach ($data['doctors'] as $d) {
                $stmt->execute([$d['id'], $d['name'], $d['qualifications'] ?? '', $d['slmc_reg'] ?? '', $d['created_at'] ?? date('Y-m-d H:i:s')]);
            }
        }

        // Restore Patients
        if (!empty($data['patients'])) {
            $stmt = $pdo->prepare("INSERT INTO `patients` (`id`, `patient_code`, `name`, `age`, `gender`, `phone`, `address`, `allergies`, `blood_group`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($data['patients'] as $p) {
                $stmt->execute([
                    $p['id'], $p['patient_code'], $p['name'], $p['age'], $p['gender'],
                    $p['phone'] ?? '', $p['address'] ?? '', $p['allergies'] ?? '', $p['blood_group'] ?? '', $p['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
        }

        // Restore Medicines
        if (!empty($data['medicines'])) {
            $stmt = $pdo->prepare("INSERT INTO `medicines` (`id`, `name`, `generic_name`, `type`, `default_strength`, `default_dosage`, `default_instruction`, `unit_price`, `stock_qty`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($data['medicines'] as $m) {
                $stmt->execute([
                    $m['id'], $m['name'], $m['generic_name'] ?? '', $m['type'] ?? 'Tablet',
                    $m['default_strength'] ?? '', $m['default_dosage'] ?? '', $m['default_instruction'] ?? '',
                    $m['unit_price'] ?? 10.00, $m['stock_qty'] ?? 500, $m['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
        }

        // Restore Templates
        if (!empty($data['templates'])) {
            $stmt = $pdo->prepare("INSERT INTO `templates` (`id`, `name`, `description`, `created_at`) VALUES (?, ?, ?, ?)");
            foreach ($data['templates'] as $t) {
                $stmt->execute([$t['id'], $t['name'], $t['description'] ?? '', $t['created_at'] ?? date('Y-m-d H:i:s')]);
            }
        }

        // Restore Template Items
        if (!empty($data['template_items'])) {
            $stmt = $pdo->prepare("INSERT INTO `template_items` (`id`, `template_id`, `medicine_name`, `strength`, `dosage`, `instruction`, `duration`, `quantity`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($data['template_items'] as $ti) {
                $stmt->execute([
                    $ti['id'], $ti['template_id'], $ti['medicine_name'], $ti['strength'] ?? '',
                    $ti['dosage'] ?? '', $ti['instruction'] ?? '', $ti['duration'] ?? '', $ti['quantity'] ?? ''
                ]);
            }
        }

        // Restore Prescriptions
        if (!empty($data['prescriptions'])) {
            $stmt = $pdo->prepare("INSERT INTO `prescriptions` (`id`, `rx_number`, `patient_id`, `bp`, `pulse`, `weight`, `temp`, `chief_complaint`, `diagnosis`, `advice`, `follow_up_date`, `consultation_fee`, `medicines_total`, `discount`, `grand_total`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($data['prescriptions'] as $rx) {
                $stmt->execute([
                    $rx['id'], $rx['rx_number'], $rx['patient_id'], $rx['bp'] ?? '', $rx['pulse'] ?? '',
                    $rx['weight'] ?? '', $rx['temp'] ?? '', $rx['chief_complaint'] ?? '', $rx['diagnosis'] ?? '',
                    $rx['advice'] ?? '', $rx['follow_up_date'] ?? null, $rx['consultation_fee'] ?? 1000.00,
                    $rx['medicines_total'] ?? 0.00, $rx['discount'] ?? 0.00, $rx['grand_total'] ?? 1000.00, $rx['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
        }

        // Restore Prescription Items
        if (!empty($data['prescription_items'])) {
            $stmt = $pdo->prepare("INSERT INTO `prescription_items` (`id`, `prescription_id`, `medicine_name`, `strength`, `dosage`, `instruction`, `duration`, `quantity`, `unit_price`, `item_total`, `item_source`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($data['prescription_items'] as $ri) {
                $stmt->execute([
                    $ri['id'], $ri['prescription_id'], $ri['medicine_name'], $ri['strength'] ?? '',
                    $ri['dosage'] ?? '', $ri['instruction'] ?? '', $ri['duration'] ?? '', $ri['quantity'] ?? '',
                    $ri['unit_price'] ?? 0.00, $ri['item_total'] ?? 0.00, $ri['item_source'] ?? 'clinic'
                ]);
            }
        }

        // Restore Waiting Queue
        if (!empty($data['waiting_queue'])) {
            $stmt = $pdo->prepare("INSERT INTO `waiting_queue` (`id`, `patient_id`, `doctor_id`, `token_number`, `status`, `created_at`) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($data['waiting_queue'] as $q) {
                $stmt->execute([$q['id'], $q['patient_id'], $q['doctor_id'], $q['token_number'], $q['status'] ?? 'Waiting', $q['created_at'] ?? date('Y-m-d H:i:s')]);
            }
        }

        // Restore Channeling Bills
        if (!empty($data['channeling_bills'])) {
            $stmt = $pdo->prepare("INSERT INTO `channeling_bills` (`id`, `bill_number`, `patient_id`, `doctor_id`, `token_number`, `doctor_fee`, `hospital_fee`, `total_amount`, `payment_status`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($data['channeling_bills'] as $cb) {
                $stmt->execute([
                    $cb['id'], $cb['bill_number'], $cb['patient_id'], $cb['doctor_id'], $cb['token_number'],
                    $cb['doctor_fee'] ?? 0.00, $cb['hospital_fee'] ?? 0.00, $cb['total_amount'] ?? 0.00, $cb['payment_status'] ?? 'Paid', $cb['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Database successfully restored from backup! Safety snapshot created.',
            'safety_snapshot' => basename($safetyFilename)
        ]);
        exit;
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Restore failed: ' . $e->getMessage()]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
