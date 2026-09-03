<?php
// api/queue.php - Live Patient Queue Management API
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';
verify_api_request();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'stream') {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $docStmt = $pdo->query("SELECT * FROM `doctors` ORDER BY `name` ASC");
        $doctors = $docStmt->fetchAll();

        $data = [];
        foreach ($doctors as $d) {
            $stmt = $pdo->prepare("SELECT q.*, p.name as patient_name, p.patient_code, p.age as patient_age, p.gender as patient_gender, p.phone as patient_phone, p.allergies as patient_allergies, p.blood_group as patient_blood_group
                FROM `waiting_queue` q
                JOIN `patients` p ON q.patient_id = p.id
                WHERE q.doctor_id = ? 
                  AND q.status IN ('Waiting', 'Serving')
                  AND DATE(q.created_at) = CURDATE()
                ORDER BY q.token_number ASC");
            $stmt->execute([$d['id']]);
            $data[] = [
                'doctor' => $d,
                'queue' => $stmt->fetchAll()
            ];
        }

        echo "retry: 3000\n";
        echo "data: " . json_encode(['status' => 'success', 'data' => $data]) . "\n\n";
        @ob_flush();
        @flush();
        exit;
    }

    $all_doctors = isset($_GET['all_doctors']) && $_GET['all_doctors'] === 'true';

    if ($all_doctors) {
        try {
            $docStmt = $pdo->query("SELECT * FROM `doctors` ORDER BY `name` ASC");
            $doctors = $docStmt->fetchAll();

            $data = [];
            foreach ($doctors as $d) {
                $stmt = $pdo->prepare("SELECT q.*, p.name as patient_name, p.patient_code, p.age as patient_age, p.gender as patient_gender, p.phone as patient_phone, p.allergies as patient_allergies, p.blood_group as patient_blood_group
                    FROM `waiting_queue` q
                    JOIN `patients` p ON q.patient_id = p.id
                    WHERE q.doctor_id = ? 
                      AND q.status IN ('Waiting', 'Serving')
                      AND DATE(q.created_at) = CURDATE()
                    ORDER BY q.token_number ASC");
                $stmt->execute([$d['id']]);
                $queue = $stmt->fetchAll();

                $data[] = [
                    'doctor' => $d,
                    'queue' => $queue
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    $doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 1;

    try {
        $stmt = $pdo->prepare("SELECT q.*, p.name as patient_name, p.patient_code, p.age as patient_age, p.gender as patient_gender, p.phone as patient_phone, p.allergies as patient_allergies, p.blood_group as patient_blood_group
            FROM `waiting_queue` q
            JOIN `patients` p ON q.patient_id = p.id
            WHERE q.doctor_id = ? 
              AND q.status IN ('Waiting', 'Serving')
              AND DATE(q.created_at) = CURDATE()
            ORDER BY q.token_number ASC");
        $stmt->execute([$doctor_id]);
        $queue = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $queue]);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $patient_id = intval($data['patient_id'] ?? 0);
    $doctor_id = intval($data['doctor_id'] ?? 1);

    if ($patient_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Valid Patient ID is required']);
        exit;
    }

    try {
        // 1. Check if patient is already in today's active queue for this doctor
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `waiting_queue` 
            WHERE `patient_id` = ? AND `doctor_id` = ? AND `status` IN ('Waiting', 'Serving') AND DATE(`created_at`) = CURDATE()");
        $stmt->execute([$patient_id, $doctor_id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Patient is already in today\'s waiting queue.']);
            exit;
        }

        // 2. Get next token number
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(`token_number`), 0) + 1 FROM `waiting_queue` 
            WHERE `doctor_id` = ? AND DATE(`created_at`) = CURDATE()");
        $stmt->execute([$doctor_id]);
        $nextToken = $stmt->fetchColumn();

        // 3. Insert into queue
        $stmt = $pdo->prepare("INSERT INTO `waiting_queue` (`patient_id`, `doctor_id`, `token_number`, `status`) VALUES (?, ?, ?, 'Waiting')");
        $stmt->execute([$patient_id, $doctor_id, $nextToken]);
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Patient added to waiting queue successfully', 
            'token_number' => $nextToken
        ]);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = intval($data['id'] ?? 0);
    $status = trim($data['status'] ?? '');

    if ($id <= 0 || !in_index($status, ['Waiting', 'Serving', 'Completed', 'Cancelled'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid queue item ID or status']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE `waiting_queue` SET `status` = ? WHERE `id` = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['status' => 'success', 'message' => 'Queue status updated successfully']);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Valid queue item ID is required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE `waiting_queue` SET `status` = 'Cancelled' WHERE `id` = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Queue item cancelled successfully']);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Helper function
function in_index($val, $arr) {
    return in_array($val, $arr);
}
