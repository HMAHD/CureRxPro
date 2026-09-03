<?php
// api/backup.php - 1-Click Complete Database Backup & Safety Restore API
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';
verify_api_request();

function generate_full_backup($pdo) {
    return [
        'version' => '2.5.0',
        'exported_at' => date('Y-m-d H:i:s'),
        'settings' => $pdo->query("SELECT * FROM `settings`")->fetchAll(),
        'doctors' => $pdo->query("SELECT * FROM `doctors`")->fetchAll(),
        'patients' => $pdo->query("SELECT * FROM `patients`")->fetchAll(),
        'medicines' => $pdo->query("SELECT * FROM `medicines`")->fetchAll(),
        'templates' => $pdo->query("SELECT * FROM `templates`")->fetchAll(),
        'template_items' => $pdo->query("SELECT * FROM `template_items`")->fetchAll(),
        'prescriptions' => $pdo->query("SELECT * FROM `prescriptions`")->fetchAll(),
        'prescription_items' => $pdo->query("SELECT * FROM `prescription_items`")->fetchAll(),
        'waiting_queue' => $pdo->query("SELECT * FROM `waiting_queue`")->fetchAll(),
        'channeling_bills' => $pdo->query("SELECT * FROM `channeling_bills`")->fetchAll()
    ];
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'export') {
    try {
        $backup = generate_full_backup($pdo);
        $filename = 'CureRx_Pro_Backup_' . date('Y-m-d_His') . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
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

        $fileContent = file_get_contents($_FILES['backup_file']['tmp_name']);
        $data = json_decode($fileContent, true);

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

        // Clear existing tables
        $pdo->exec("TRUNCATE TABLE `channeling_bills`;");
        $pdo->exec("TRUNCATE TABLE `waiting_queue`;");
        $pdo->exec("TRUNCATE TABLE `prescription_items`;");
        $pdo->exec("TRUNCATE TABLE `prescriptions`;");
        $pdo->exec("TRUNCATE TABLE `template_items`;");
        $pdo->exec("TRUNCATE TABLE `templates`;");
        $pdo->exec("TRUNCATE TABLE `medicines`;");
        $pdo->exec("TRUNCATE TABLE `patients`;");
        $pdo->exec("TRUNCATE TABLE `doctors`;");

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
