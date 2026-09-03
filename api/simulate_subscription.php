<?php
// api/simulate_subscription.php - Helper script to simulate subscription states for testing
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? null;

if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'Action parameter is required (?action=expire or ?action=activate)']);
    exit;
}

try {
    if ($action === 'expire') {
        $expiry = date('Y-m-d', strtotime('-10 days')); // Expired 10 days ago
        $stmt = $pdo->prepare("UPDATE `settings` SET `subscription_status` = 'expired', `subscription_expires_at` = ? WHERE `id` = 1");
        $stmt->execute([$expiry]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Subscription successfully set to EXPIRED for testing',
            'new_status' => 'expired',
            'new_expiry' => $expiry
        ]);
        exit;
    } 
    
    if ($action === 'activate') {
        $expiry = date('Y-m-d', strtotime('+30 days')); // Active for 30 days
        $stmt = $pdo->prepare("UPDATE `settings` SET `subscription_status` = 'active', `subscription_expires_at` = ? WHERE `id` = 1");
        $stmt->execute([$expiry]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Subscription successfully set to ACTIVE (30 days trial) for testing',
            'new_status' => 'active',
            'new_expiry' => $expiry
        ]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action. Use "expire" or "activate"']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database update failed: ' . $e->getMessage()]);
}
