<?php
// api/channeling.php - Manage channeling bills and queues for front desk receptionists
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';
verify_api_request();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $patient_id = intval($data['patient_id'] ?? 0);
    $doctor_id = intval($data['doctor_id'] ?? 1);
    $doctor_fee = floatval($data['doctor_fee'] ?? 0.00);
    $hospital_fee = floatval($data['hospital_fee'] ?? 0.00);
    $total_amount = $doctor_fee + $hospital_fee;
    $payment_status = trim($data['payment_status'] ?? 'Paid');
    if (!in_array($payment_status, ['Paid', 'Unpaid'])) {
        $payment_status = 'Paid';
    }

    if ($patient_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a valid patient']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        // 1. Generate unique bill number
        $bill_number = 'CHL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        // 2. Get next token number for this doctor today
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(`token_number`), 0) + 1 FROM `waiting_queue` 
            WHERE `doctor_id` = ? AND DATE(`created_at`) = CURDATE()");
        $stmt->execute([$doctor_id]);
        $token_number = $stmt->fetchColumn();

        // 3. Insert into waiting queue
        $queueStmt = $pdo->prepare("INSERT INTO `waiting_queue` (`patient_id`, `doctor_id`, `token_number`, `status`) VALUES (?, ?, ?, 'Waiting')");
        $queueStmt->execute([$patient_id, $doctor_id, $token_number]);

        // 4. Insert into channeling bills
        $billStmt = $pdo->prepare("INSERT INTO `channeling_bills` 
            (`bill_number`, `patient_id`, `doctor_id`, `token_number`, `doctor_fee`, `hospital_fee`, `total_amount`, `payment_status`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $billStmt->execute([
            $bill_number, $patient_id, $doctor_id, $token_number, $doctor_fee, $hospital_fee, $total_amount, $payment_status
        ]);
        
        $bill_id = $pdo->lastInsertId();

        $pdo->commit();

        // 5. Fetch completed details for printing
        $fetchStmt = $pdo->prepare("SELECT cb.*, p.name as patient_name, p.patient_code, p.age as patient_age, p.gender as patient_gender,
            d.name as doctor_name, d.qualifications as qualifications
            FROM `channeling_bills` cb
            JOIN `patients` p ON cb.patient_id = p.id
            JOIN `doctors` d ON cb.doctor_id = d.id
            WHERE cb.id = ?");
        $fetchStmt->execute([$bill_id]);
        $bill_details = $fetchStmt->fetch();

        echo json_encode([
            'status' => 'success',
            'message' => 'Channeling bill saved and patient queued successfully!',
            'data' => $bill_details
        ]);
    } catch (\Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'GET') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT cb.*, p.name as patient_name, p.patient_code, p.age as patient_age, p.gender as patient_gender, p.phone as patient_phone,
                d.name as doctor_name, d.qualifications as qualifications, d.slmc_reg as doctor_slmc
                FROM `channeling_bills` cb
                JOIN `patients` p ON cb.patient_id = p.id
                JOIN `doctors` d ON cb.doctor_id = d.id
                WHERE cb.id = ?");
            $stmt->execute([$id]);
            $bill = $stmt->fetch();
            echo json_encode(['status' => 'success', 'data' => $bill]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Default: return list of today's channeling bills
    try {
        $stmt = $pdo->query("SELECT cb.*, p.name as patient_name, p.patient_code, d.name as doctor_name
            FROM `channeling_bills` cb
            JOIN `patients` p ON cb.patient_id = p.id
            JOIN `doctors` d ON cb.doctor_id = d.id
            WHERE DATE(cb.created_at) = CURDATE()
            ORDER BY cb.id DESC");
        $bills = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $bills]);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Valid ID is required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE `channeling_bills` SET `payment_status` = 'Paid' WHERE `id` = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Payment status updated successfully']);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
