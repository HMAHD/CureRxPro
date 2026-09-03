<?php
// api/medicines.php - Drug Master Database & Pricing API
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';
verify_api_request();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $query = trim($_GET['q'] ?? '');
    $type = trim($_GET['type'] ?? 'all');

    $sql = "SELECT * FROM `medicines` WHERE 1=1";
    $params = [];

    if ($query !== '') {
        $sql .= " AND (`name` LIKE ? OR `generic_name` LIKE ?)";
        $term = "%$query%";
        $params[] = $term;
        $params[] = $term;
    }

    if ($type !== 'all' && $type !== '') {
        $sql .= " AND `type` = ?";
        $params[] = $type;
    }

    $sql .= " ORDER BY `name` ASC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $medicines = $stmt->fetchAll();
    echo json_encode(['status' => 'success', 'data' => $medicines]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $name = trim($data['name'] ?? '');
    $generic_name = trim($data['generic_name'] ?? '');
    $type = trim($data['type'] ?? 'Tablet');
    $default_strength = trim($data['default_strength'] ?? '');
    $default_dosage = trim($data['default_dosage'] ?? '');
    $default_instruction = trim($data['default_instruction'] ?? '');
    $unit_price = floatval($data['unit_price'] ?? 10.00);
    $stock_qty = intval($data['stock_qty'] ?? 500);

    if (empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Medicine name is required']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO `medicines` (`name`, `generic_name`, `type`, `default_strength`, `default_dosage`, `default_instruction`, `unit_price`, `stock_qty`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $generic_name, $type, $default_strength, $default_dosage, $default_instruction, $unit_price, $stock_qty]);
    $newId = $pdo->lastInsertId();

    echo json_encode(['status' => 'success', 'message' => 'Medicine added successfully', 'id' => $newId]);
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM `medicines` WHERE `id` = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Medicine deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Medicine ID required']);
    }
    exit;
}
