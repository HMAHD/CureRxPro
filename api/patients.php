<?php
// api/patients.php - Patient Management API
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search = trim($_GET['q'] ?? '');
    $id = $_GET['id'] ?? null;

    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM `patients` WHERE `id` = ?");
        $stmt->execute([$id]);
        $patient = $stmt->fetch();

        if ($patient) {
            // Get past prescriptions count & list
            $rxStmt = $pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as item_count FROM prescriptions p WHERE p.patient_id = ? ORDER BY p.created_at DESC");
            $rxStmt->execute([$id]);
            $patient['history'] = $rxStmt->fetchAll();
        }

        echo json_encode(['status' => 'success', 'data' => $patient]);
        exit;
    }

    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT * FROM `patients` WHERE `name` LIKE ? OR `phone` LIKE ? OR `patient_code` LIKE ? ORDER BY `id` DESC LIMIT 20");
        $searchTerm = "%$search%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    } else {
        $stmt = $pdo->query("SELECT * FROM `patients` ORDER BY `id` DESC LIMIT 100");
    }

    $patients = $stmt->fetchAll();
    echo json_encode(['status' => 'success', 'data' => $patients]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $id = $data['id'] ?? null;
    $name = trim($data['name'] ?? '');
    $age = intval($data['age'] ?? 0);
    $gender = trim($data['gender'] ?? 'Male');
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $allergies = trim($data['allergies'] ?? '');
    $blood_group = trim($data['blood_group'] ?? '');

    if (empty($name) || $age <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Valid patient name and age are required']);
        exit;
    }

    if ($id) {
        // Update existing patient
        $stmt = $pdo->prepare("UPDATE `patients` SET `name` = ?, `age` = ?, `gender` = ?, `phone` = ?, `address` = ?, `allergies` = ?, `blood_group` = ? WHERE `id` = ?");
        $stmt->execute([$name, $age, $gender, $phone, $address, $allergies, $blood_group, $id]);
        echo json_encode(['status' => 'success', 'message' => 'Patient details updated successfully', 'patient_id' => $id]);
    } else {
        // Create new patient
        $patient_code = 'P-' . strtoupper(substr(uniqid(), -6));
        $stmt = $pdo->prepare("INSERT INTO `patients` (`patient_code`, `name`, `age`, `gender`, `phone`, `address`, `allergies`, `blood_group`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$patient_code, $name, $age, $gender, $phone, $address, $allergies, $blood_group]);
        $newId = $pdo->lastInsertId();

        $fetchStmt = $pdo->prepare("SELECT * FROM `patients` WHERE `id` = ?");
        $fetchStmt->execute([$newId]);
        $newPatient = $fetchStmt->fetch();

        echo json_encode(['status' => 'success', 'message' => 'Patient created successfully', 'data' => $newPatient]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM `patients` WHERE `id` = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Patient deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Patient ID required']);
    }
    exit;
}
