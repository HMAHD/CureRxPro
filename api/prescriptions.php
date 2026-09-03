<?php
// api/prescriptions.php - Save & Manage Prescriptions and Dispensary Billing API
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';
verify_api_request();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    if ($id) {
        $stmt = $pdo->prepare("SELECT p.*, 
            pt.name as patient_name, pt.age as patient_age, pt.gender as patient_gender, pt.phone as patient_phone, pt.address as patient_address, pt.allergies as patient_allergies, pt.blood_group as patient_blood_group, pt.patient_code,
            d.name as doctor_name, d.qualifications as qualifications, d.slmc_reg as slmc_reg
            FROM `prescriptions` p 
            JOIN `patients` pt ON p.patient_id = pt.id 
            LEFT JOIN `doctors` d ON p.doctor_id = d.id
            WHERE p.id = ?");
        $stmt->execute([$id]);
        $rx = $stmt->fetch();

        if ($rx) {
            $itemsStmt = $pdo->prepare("SELECT * FROM `prescription_items` WHERE `prescription_id` = ?");
            $itemsStmt->execute([$id]);
            $rx['items'] = $itemsStmt->fetchAll();

            $settStmt = $pdo->query("SELECT * FROM `settings` LIMIT 1");
            $settings = $settStmt->fetch();
            if ($settings) {
                $settings['doctor_name'] = $rx['doctor_name'] ?: $settings['doctor_name'];
                $settings['qualifications'] = $rx['qualifications'] ?: $settings['qualifications'];
                $settings['slmc_reg'] = $rx['slmc_reg'] ?: $settings['slmc_reg'];
            }
            $rx['settings'] = $settings;
        }

        echo json_encode(['status' => 'success', 'data' => $rx]);
        exit;
    }

    $patient_id = $_GET['patient_id'] ?? null;
    if ($patient_id) {
        $all = isset($_GET['all']) && $_GET['all'] === 'true';

        if ($all) {
            // Fetch all prescriptions with doctor details
            $stmt = $pdo->prepare("SELECT p.*, pt.name as patient_name, pt.age as patient_age, pt.gender as patient_gender, pt.phone as patient_phone, pt.patient_code,
                d.name as doctor_name, d.qualifications as qualifications, d.slmc_reg as slmc_reg
                FROM `prescriptions` p 
                JOIN `patients` pt ON p.patient_id = pt.id 
                LEFT JOIN `doctors` d ON p.doctor_id = d.id
                WHERE p.patient_id = ? 
                ORDER BY p.id DESC");
            $stmt->execute([$patient_id]);
            $rxs = $stmt->fetchAll();

            foreach ($rxs as &$rx) {
                $itemsStmt = $pdo->prepare("SELECT * FROM `prescription_items` WHERE `prescription_id` = ?");
                $itemsStmt->execute([$rx['id']]);
                $rx['items'] = $itemsStmt->fetchAll();
            }

            echo json_encode(['status' => 'success', 'data' => $rxs]);
            exit;
        } else {
            $stmt = $pdo->prepare("SELECT p.*, pt.name as patient_name, pt.age as patient_age, pt.gender as patient_gender, pt.phone as patient_phone, pt.patient_code 
                FROM `prescriptions` p 
                JOIN `patients` pt ON p.patient_id = pt.id 
                WHERE p.patient_id = ? 
                ORDER BY p.id DESC LIMIT 1");
            $stmt->execute([$patient_id]);
            $rx = $stmt->fetch();

            if ($rx) {
                $itemsStmt = $pdo->prepare("SELECT * FROM `prescription_items` WHERE `prescription_id` = ?");
                $itemsStmt->execute([$rx['id']]);
                $rx['items'] = $itemsStmt->fetchAll();
            }

            echo json_encode(['status' => 'success', 'data' => $rx]);
            exit;
        }
    }

    $search = trim($_GET['q'] ?? '');
    $doctor_filter = $_GET['doctor_id'] ?? 'all';

    $query = "SELECT p.*, pt.name as patient_name, pt.patient_code, (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as item_count 
              FROM `prescriptions` p 
              JOIN `patients` pt ON p.patient_id = pt.id";
    $conditions = [];
    $params = [];

    if ($search !== '') {
        $conditions[] = "(p.rx_number LIKE ? OR pt.name LIKE ? OR pt.phone LIKE ?)";
        $term = "%$search%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    if ($doctor_filter !== 'all') {
        $conditions[] = "p.doctor_id = ?";
        $params[] = intval($doctor_filter);
    }

    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY p.id DESC LIMIT 50";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $prescriptions = $stmt->fetchAll();
    echo json_encode(['status' => 'success', 'data' => $prescriptions]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $patient_id = intval($data['patient_id'] ?? 0);
    $bp = trim($data['bp'] ?? '');
    $pulse = trim($data['pulse'] ?? '');
    $weight = trim($data['weight'] ?? '');
    $temp = trim($data['temp'] ?? '');
    $chief_complaint = trim($data['chief_complaint'] ?? '');
    $diagnosis = trim($data['diagnosis'] ?? '');
    $advice = trim($data['advice'] ?? '');
    $follow_up_date = !empty($data['follow_up_date']) ? $data['follow_up_date'] : null;

    $consultation_fee = floatval($data['consultation_fee'] ?? 1000.00);
    $hospital_fee = floatval($data['hospital_fee'] ?? 0.00);
    $medicines_total = floatval($data['medicines_total'] ?? 0.00);
    $discount = floatval($data['discount'] ?? 0.00);
    $grand_total = floatval($data['grand_total'] ?? ($consultation_fee + $hospital_fee + $medicines_total - $discount));

    $items = $data['items'] ?? [];

    if ($patient_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please select or create a valid patient']);
        exit;
    }

    if (empty($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Please add at least one medicine to the prescription']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $rx_number = 'RX-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        $doctor_id = intval($data['doctor_id'] ?? 1);

        $stmt = $pdo->prepare("INSERT INTO `prescriptions` 
            (`rx_number`, `patient_id`, `bp`, `pulse`, `weight`, `temp`, `chief_complaint`, `diagnosis`, `advice`, `follow_up_date`, `consultation_fee`, `hospital_fee`, `medicines_total`, `discount`, `grand_total`, `doctor_id`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $rx_number, $patient_id, $bp, $pulse, $weight, $temp,
            $chief_complaint, $diagnosis, $advice, $follow_up_date,
            $consultation_fee, $hospital_fee, $medicines_total, $discount, $grand_total,
            $doctor_id
        ]);

        $rx_id = $pdo->lastInsertId();

        $itemStmt = $pdo->prepare("INSERT INTO `prescription_items` 
            (`prescription_id`, `medicine_name`, `strength`, `dosage`, `instruction`, `duration`, `quantity`, `unit_price`, `item_total`, `item_source`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($items as $item) {
            $uPrice = floatval($item['unit_price'] ?? 0.00);
            $iTotal = floatval($item['item_total'] ?? 0.00);
            $itemSource = trim($item['item_source'] ?? 'clinic');

            $itemStmt->execute([
                $rx_id,
                trim($item['medicine_name'] ?? ''),
                trim($item['strength'] ?? ''),
                trim($item['dosage'] ?? ''),
                trim($item['instruction'] ?? ''),
                trim($item['duration'] ?? ''),
                trim($item['quantity'] ?? ''),
                $uPrice,
                $iTotal,
                $itemSource
            ]);

            // Atomic Inventory Stock Deduction Guard (Issue #10)
            if ($itemSource === 'clinic') {
                $rawQty = trim($item['quantity'] ?? '');
                $numericQty = intval(preg_replace('/[^0-9]/', '', $rawQty));
                if ($numericQty <= 0) $numericQty = 1;

                $stockStmt = $pdo->prepare("UPDATE `medicines` SET `stock_qty` = GREATEST(0, `stock_qty` - ?) WHERE `name` = ?");
                $stockStmt->execute([$numericQty, trim($item['medicine_name'] ?? '')]);
            }
        }

        // Mark today's active queue item as Completed for this doctor and patient
        $queueStmt = $pdo->prepare("UPDATE `waiting_queue` SET `status` = 'Completed' 
            WHERE `patient_id` = ? AND `doctor_id` = ? AND `status` IN ('Waiting', 'Serving') AND DATE(`created_at`) = CURDATE()");
        $queueStmt->execute([$patient_id, $doctor_id]);

        $pdo->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'Prescription & Dispensary Bill saved successfully', 
            'prescription_id' => $rx_id,
            'rx_number' => $rx_number
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Failed to save prescription: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM `prescriptions` WHERE `id` = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Prescription deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Prescription ID required']);
    }
    exit;
}
