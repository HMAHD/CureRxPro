<?php
// api/settings.php - Clinic Settings & License Management API
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';
verify_api_request();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM `settings` LIMIT 1");
    $settings = $stmt->fetch();

    if ($settings) {
        $today = new DateTime(date('Y-m-d'));
        $expDateStr = !empty($settings['subscription_expires_at']) ? $settings['subscription_expires_at'] : date('Y-m-d');
        $exp = new DateTime($expDateStr);

        if ($exp < $today) {
            $settings['subscription_status'] = 'expired';
            $settings['days_remaining'] = 0;
        } else {
            $settings['subscription_status'] = 'active';
            $interval = $today->diff($exp);
            $settings['days_remaining'] = (int)$interval->format('%a');
        }
    }

    echo json_encode(['status' => 'success', 'data' => $settings]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $action = $data['action'] ?? 'update_profile';

    if ($action === 'set_active_doctor') {
        $doctor_id = intval($data['active_doctor_id'] ?? 1);
        $stmt = $pdo->prepare("UPDATE `settings` SET `active_doctor_id` = ? WHERE `id` = 1");
        $stmt->execute([$doctor_id]);
        echo json_encode(['status' => 'success', 'message' => 'Active doctor updated successfully']);
        exit;
    }

    if ($action === 'activate_license') {
        $license_key = trim($data['license_key'] ?? '');
        $upperKey = strtoupper($license_key);
        
        if (str_contains($upperKey, 'CURERX') || str_contains($upperKey, 'CRX')) {
            $currStmt = $pdo->query("SELECT * FROM `settings` LIMIT 1");
            $curr = $currStmt->fetch();

            $today = date('Y-m-d');
            $baseDate = $today;

            if ($curr && !empty($curr['subscription_expires_at']) && $curr['subscription_expires_at'] >= $today) {
                $baseDate = $curr['subscription_expires_at'];
            }

            $days = 30;
            if (str_contains($upperKey, 'YEAR') || str_contains($upperKey, '365')) {
                $days = 365;
            } else if (str_contains($upperKey, '6MONTH') || str_contains($upperKey, '180')) {
                $days = 180;
            }

            $newExpiry = date('Y-m-d', strtotime("$baseDate +$days days"));

            $stmt = $pdo->prepare("UPDATE `settings` SET `subscription_status` = 'active', `subscription_expires_at` = ?, `license_key` = ? WHERE `id` = 1");
            $stmt->execute([$newExpiry, $license_key]);

            $updatedStmt = $pdo->query("SELECT * FROM `settings` LIMIT 1");
            $updated = $updatedStmt->fetch();

            // Calculate days remaining for response
            $tObj = new DateTime(date('Y-m-d'));
            $eObj = new DateTime($newExpiry);
            $diffObj = $tObj->diff($eObj);
            $updated['days_remaining'] = (int)$diffObj->format('%a');
            $updated['subscription_status'] = 'active';

            echo json_encode([
                'status' => 'success', 
                'message' => "License activated successfully! Extended by $days days. Active until: $newExpiry", 
                'data' => $updated
            ]);
        } else {
            // Treat as Registered Email Address and verify online
            if (!filter_var($license_key, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['status' => 'error', 'message' => 'Please enter a valid registered email address or offline license key']);
                exit;
            }

            // Call online licensing API
            $url = "https://smarthub.web.lk/licensing/check.php?key=" . urlencode($license_key);
            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $response = @file_get_contents($url, false, $ctx);

            if ($response) {
                $resData = json_decode($response, true);
                if ($resData && isset($resData['status']) && $resData['status'] === 'success') {
                    $newExpiry = $resData['expires_at'] ?? date('Y-m-d', strtotime('+30 days'));
                    $subStatus = $resData['subscription_status'] ?? 'active';

                    if ($subStatus === 'expired') {
                        echo json_encode(['status' => 'error', 'message' => 'This account has expired. Please renew your subscription on our website.']);
                        exit;
                    }

                    $stmt = $pdo->prepare("UPDATE `settings` SET `subscription_status` = 'active', `subscription_expires_at` = ?, `license_key` = ? WHERE `id` = 1");
                    $stmt->execute([$newExpiry, $license_key]);

                    $updatedStmt = $pdo->query("SELECT * FROM `settings` LIMIT 1");
                    $updated = $updatedStmt->fetch();
                    $tObj = new DateTime(date('Y-m-d'));
                    $eObj = new DateTime($newExpiry);
                    $diffObj = $tObj->diff($eObj);
                    $updated['days_remaining'] = (int)$diffObj->format('%a');
                    $updated['subscription_status'] = 'active';

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Subscription activated successfully via online email check!',
                        'data' => $updated
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => $resData['message'] ?? 'This email is not registered on our licensing server. Please register first.'
                    ]);
                }
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Unable to connect to the online licensing server. Please check your internet connection and try again.'
                ]);
            }
        }
        exit;
    }

    if ($action === 'sync_subscription') {
        $expires_at = trim($data['expires_at'] ?? '');
        $status = trim($data['status'] ?? 'active');
        $plan = trim($data['plan'] ?? 'Monthly Pro (LKR 3,000/mo)');

        if (empty($expires_at)) {
            echo json_encode(['status' => 'error', 'message' => 'Expiry date is required']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE `settings` SET `subscription_expires_at` = ?, `subscription_status` = ?, `subscription_plan` = ? WHERE `id` = 1");
        $stmt->execute([$expires_at, $status, $plan]);

        echo json_encode(['status' => 'success', 'message' => 'Subscription synced successfully', 'expires_at' => $expires_at]);
        exit;
    }

    // Default Profile Update
    $doctor_name = trim($data['doctor_name'] ?? '');
    $qualifications = trim($data['qualifications'] ?? '');
    $slmc_reg = trim($data['slmc_reg'] ?? '');
    $clinic_name = trim($data['clinic_name'] ?? '');
    $clinic_address = trim($data['clinic_address'] ?? '');
    $clinic_phone = trim($data['clinic_phone'] ?? '');
    $print_paper_size = trim($data['print_paper_size'] ?? 'A5');
    $default_language = trim($data['default_language'] ?? 'bilingual');
    $practice_mode = trim($data['practice_mode'] ?? 'dispensary');

    $stmt = $pdo->prepare("UPDATE `settings` SET 
        `doctor_name` = ?, 
        `qualifications` = ?, 
        `slmc_reg` = ?, 
        `clinic_name` = ?, 
        `clinic_address` = ?, 
        `clinic_phone` = ?, 
        `print_paper_size` = ?, 
        `default_language` = ?,
        `practice_mode` = ?
        WHERE `id` = 1");

    $stmt->execute([
        $doctor_name, $qualifications, $slmc_reg, $clinic_name,
        $clinic_address, $clinic_phone, $print_paper_size, $default_language,
        $practice_mode
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Settings updated successfully']);
    exit;
}
