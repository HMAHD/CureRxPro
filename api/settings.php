<?php
// api/settings.php - Clinic Settings & License Management API
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';
verify_api_request();

// HMAC-SHA256 signed offline license key validator.
// Expected format: CRX-{payload}-{hmac}
// payload = base64url(plan|expiry_unix)
function validate_license_key(string $key, string $secret): array|false {
    $parts = explode('-', $key, 3); // ['CRX', payload, hmac]
    if (count($parts) !== 3) {
        return false;
    }
    [, $payload, $providedHmac] = $parts;
    $signingKey = hash('sha256', $secret, true);
    $expectedHmac = hash_hmac('sha256', $payload, $signingKey);
    if (!hash_equals($expectedHmac, $providedHmac)) {
        return false;
    }
    // Decode payload: plan|expiry_unix
    $decoded = base64_decode(strtr($payload, '-_', '+/'));
    if ($decoded === false) {
        return false;
    }
    $splitParts = explode('|', $decoded, 2) + ['', '0'];
    [$plan, $expiry] = $splitParts;
    if ((int)$expiry !== 0 && (int)$expiry < time()) {
        return false; // expired
    }
    if (!in_array($plan, ['starter', 'pro', 'clinic'], true)) {
        return false;
    }
    return ['plan' => $plan, 'expiry' => (int)$expiry];
}

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

        if (str_starts_with($license_key, 'CRX-')) {
            // Load the installation secret
            $secretFile = getenv('USER_DATA_PATH')
                ? getenv('USER_DATA_PATH') . '/api_secret.key'
                : __DIR__ . '/../api_secret.key';
            $installSecret = file_exists($secretFile) ? trim(file_get_contents($secretFile)) : '';

            $keyData = validate_license_key($license_key, $installSecret);
            if ($keyData === false) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Invalid or expired license key']);
                exit;
            }

            $newExpiry = $keyData['expiry'] !== 0
                ? date('Y-m-d', $keyData['expiry'])
                : date('Y-m-d', strtotime('+30 days'));

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
                'message' => "License activated successfully! Active until: $newExpiry",
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
    $clinic_logo = trim($data['clinic_logo'] ?? '');
    $print_paper_size = trim($data['print_paper_size'] ?? 'A5');
    $default_language = trim($data['default_language'] ?? 'bilingual');
    $practice_mode = trim($data['practice_mode'] ?? 'dispensary');

    // Ensure clinic_logo column exists
    try { $pdo->exec("ALTER TABLE `settings` ADD COLUMN `clinic_logo` LONGTEXT NULL"); } catch (\PDOException $e) {}

    $stmt = $pdo->prepare("UPDATE `settings` SET 
        `doctor_name` = ?, 
        `qualifications` = ?, 
        `slmc_reg` = ?, 
        `clinic_name` = ?, 
        `clinic_address` = ?, 
        `clinic_phone` = ?, 
        `clinic_logo` = ?,
        `print_paper_size` = ?, 
        `default_language` = ?,
        `practice_mode` = ?
        WHERE `id` = 1");

    $stmt->execute([
        $doctor_name, $qualifications, $slmc_reg, $clinic_name,
        $clinic_address, $clinic_phone, $clinic_logo, $print_paper_size, $default_language,
        $practice_mode
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Settings updated successfully']);
    exit;
}
