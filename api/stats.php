<?php
// api/stats.php - Dashboard Quick Stats API
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';
verify_api_request();

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

    // Financial & Operational Analytics (Issue #25)
    $todayRevenueStmt = $pdo->query("SELECT COALESCE(SUM(grand_total), 0) as total_revenue, COALESCE(SUM(consultation_fee), 0) as consultation_total, COALESCE(SUM(medicines_total), 0) as pharmacy_total FROM `prescriptions` WHERE DATE(`created_at`) = CURDATE()");
    $todayRevenue = $todayRevenueStmt->fetch();

    $monthlyRevenue = $pdo->query("SELECT COALESCE(SUM(grand_total), 0) FROM `prescriptions` WHERE MONTH(`created_at`) = MONTH(CURDATE()) AND YEAR(`created_at`) = YEAR(CURDATE())")->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'total_patients' => (int)$total_patients,
            'today_prescriptions' => (int)$today_prescriptions,
            'total_prescriptions' => (int)$total_prescriptions,
            'total_medicines' => (int)$total_medicines,
            'today_revenue' => floatval($todayRevenue['total_revenue']),
            'today_consultation_total' => floatval($todayRevenue['consultation_total']),
            'today_pharmacy_total' => floatval($todayRevenue['pharmacy_total']),
            'monthly_revenue' => floatval($monthlyRevenue),
            'recent_prescriptions' => $recent
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
