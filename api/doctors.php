<?php
// api/doctors.php - Manage multiple doctor profiles for channeling centers
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM `doctors` ORDER BY `name` ASC");
        $doctors = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $doctors]);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = isset($data['id']) ? intval($data['id']) : null;
    $name = trim($data['name'] ?? '');
    $qualifications = trim($data['qualifications'] ?? '');
    $slmc_reg = trim($data['slmc_reg'] ?? '');

    if (empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Doctor name is required']);
        exit;
    }

    try {
        if ($id) {
            // Edit existing doctor
            $stmt = $pdo->prepare("UPDATE `doctors` SET `name` = ?, `qualifications` = ?, `slmc_reg` = ? WHERE `id` = ?");
            $stmt->execute([$name, $qualifications, $slmc_reg, $id]);
            echo json_encode(['status' => 'success', 'message' => 'Doctor details updated successfully']);
        } else {
            // Add new doctor
            $stmt = $pdo->prepare("INSERT INTO `doctors` (`name`, `qualifications`, `slmc_reg`) VALUES (?, ?, ?)");
            $stmt->execute([$name, $qualifications, $slmc_reg]);
            $newId = $pdo->lastInsertId();
            echo json_encode(['status' => 'success', 'message' => 'New doctor profile added successfully', 'id' => $newId]);
        }
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;

    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'Doctor ID is required']);
        exit;
    }

    try {
        // Ensure at least one doctor remains
        $count = $pdo->query("SELECT COUNT(*) FROM `doctors`")->fetchColumn();
        if ($count <= 1) {
            echo json_encode(['status' => 'error', 'message' => 'You must keep at least one active doctor profile.']);
            exit;
        }

        // Delete doctor
        $stmt = $pdo->prepare("DELETE FROM `doctors` WHERE `id` = ?");
        $stmt->execute([$id]);

        // If settings has this doctor as active_doctor_id, reset active_doctor_id to the first available doctor
        $activeDocId = $pdo->query("SELECT `active_doctor_id` FROM `settings` LIMIT 1")->fetchColumn();
        if ($activeDocId == $id) {
            $firstDocId = $pdo->query("SELECT `id` FROM `doctors` ORDER BY `id` ASC LIMIT 1")->fetchColumn();
            $pdo->prepare("UPDATE `settings` SET `active_doctor_id` = ? WHERE `id` = 1")->execute([$firstDocId]);
        }

        echo json_encode(['status' => 'success', 'message' => 'Doctor profile deleted successfully']);
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
