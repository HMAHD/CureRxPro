<?php
// api/stats.php - Dashboard Quick Stats API
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    $total_patients = $pdo->query("SELECT COUNT(*) FROM `patients`")->fetchColumn();
    $today_prescriptions = $pdo->query("SELECT COUNT(*) FROM `prescriptions` WHERE DATE(`created_at`) = CURDATE()")->fetchColumn();
    $total_prescriptions = $pdo->query("SELECT COUNT(*) FROM `prescriptions`")->fetchColumn();
    $total_medicines = $pdo->query("SELECT COUNT(*) FROM `medicines`")->fetchColumn();

    // Fetch 5 recent prescriptions
    $recentStmt = $pdo->query("SELECT p.id, p.rx_number, p.created_at, pt.name as patient_name, pt.age, pt.gender 
        FROM `prescriptions` p 
        JOIN `patients` pt ON p.patient_id = pt.id 
        ORDER BY p.id DESC LIMIT 5");
    $recent = $recentStmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'total_patients' => (int)$total_patients,
            'today_prescriptions' => (int)$today_prescriptions,
            'total_prescriptions' => (int)$total_prescriptions,
            'total_medicines' => (int)$total_medicines,
            'recent_prescriptions' => $recent
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
