<?php
// api/templates.php - Prescription Quick Templates API
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';
verify_api_request();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM `templates` WHERE `id` = ?");
        $stmt->execute([$id]);
        $template = $stmt->fetch();

        if ($template) {
            $itemsStmt = $pdo->prepare("SELECT * FROM `template_items` WHERE `template_id` = ?");
            $itemsStmt->execute([$id]);
            $template['items'] = $itemsStmt->fetchAll();
        }

        echo json_encode(['status' => 'success', 'data' => $template]);
        exit;
    }

    $stmt = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM template_items WHERE template_id = t.id) as item_count FROM `templates` t ORDER BY `id` DESC");
    $templates = $stmt->fetchAll();

    // Fetch items for each template
    foreach ($templates as &$tpl) {
        $itemsStmt = $pdo->prepare("SELECT * FROM `template_items` WHERE `template_id` = ?");
        $itemsStmt->execute([$tpl['id']]);
        $tpl['items'] = $itemsStmt->fetchAll();
    }

    echo json_encode(['status' => 'success', 'data' => $templates]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $items = $data['items'] ?? [];

    if (empty($name) || empty($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Template name and at least one medicine item are required']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO `templates` (`name`, `description`) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        $tpl_id = $pdo->lastInsertId();

        $itemStmt = $pdo->prepare("INSERT INTO `template_items` (`template_id`, `medicine_name`, `strength`, `dosage`, `instruction`, `duration`, `quantity`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $itemStmt->execute([
                $tpl_id,
                trim($item['medicine_name'] ?? ''),
                trim($item['strength'] ?? ''),
                trim($item['dosage'] ?? ''),
                trim($item['instruction'] ?? ''),
                trim($item['duration'] ?? ''),
                trim($item['quantity'] ?? '')
            ]);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Template saved successfully', 'id' => $tpl_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Failed to save template: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM `templates` WHERE `id` = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Template deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Template ID required']);
    }
    exit;
}
