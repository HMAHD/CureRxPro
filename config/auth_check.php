<?php
// config/auth_check.php - API Token Authentication Middleware for CureRxPro

function get_api_secret() {
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }

    $userDataPath = getenv('USER_DATA_PATH');
    if ($userDataPath) {
        $keyPath = $userDataPath . '/api_secret.key';
    } else {
        $keyPath = __DIR__ . '/api_secret.key';
    }

    if (file_exists($keyPath)) {
        $secret = trim(@file_get_contents($keyPath));
        if (!empty($secret)) {
            return $secret;
        }
    }

    // Generate cryptographically secure 256-bit random secret key
    try {
        $secret = bin2hex(random_bytes(32));
    } catch (\Exception $e) {
        $secret = bin2hex(openssl_random_pseudo_bytes(32));
    }

    @file_put_contents($keyPath, $secret);
    @chmod($keyPath, 0600);
    return $secret;
}

function verify_api_request() {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLoopback = in_array($remoteAddr, ['127.0.0.1', '::1', 'localhost'], true);

    $providedToken = '';
    
    // Check HTTP headers
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $key => $val) {
            if (strtolower($key) === 'x-curerx-token') {
                $providedToken = trim($val);
                break;
            }
        }
    }

    if (empty($providedToken) && isset($_SERVER['HTTP_X_CURERX_TOKEN'])) {
        $providedToken = trim($_SERVER['HTTP_X_CURERX_TOKEN']);
    }

    $expectedSecret = get_api_secret();

    // Verify token using timing-safe comparison
    if (!empty($providedToken) && hash_equals($expectedSecret, $providedToken)) {
        return true;
    }

    // Allow loopback local traffic if no header present (Standalone mode)
    if ($isLoopback && empty($providedToken)) {
        return true;
    }

    // Unauthorized access
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized API access. Valid X-CureRx-Token header required.'
    ]);
    exit;
}
