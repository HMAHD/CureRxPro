<?php
// api/save_db_config.php - Test, discover, and save database configurations
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth_check.php';
verify_api_request();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $action = $data['action'] ?? 'save';
    $host = trim($data['host'] ?? '127.0.0.1');
    $port = trim($data['port'] ?? '3306');
    $user = trim($data['user'] ?? 'root');
    $pass = $data['pass'] ?? '';
    $dbname = trim($data['dbname'] ?? 'prescription_db');
    $app_role = trim($data['app_role'] ?? 'both'); // Options: both, doctor, receptionist
    $charset = 'utf8mb4';

    // 1. Connection Test / Database Discovery Block
    try {
        $dsn = "mysql:host=$host;port=$port;charset=$charset";
        $testPdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3
        ]);

        if ($action === 'discover') {
            // Get all databases on the server
            $stmt = $testPdo->query("SHOW DATABASES");
            $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Filter out default MySQL system databases
            $systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin'];
            $filteredDbs = array_filter($databases, function($db) use ($systemDbs) {
                return !in_array(strtolower($db), $systemDbs);
            });

            echo json_encode([
                'status' => 'success',
                'databases' => array_values($filteredDbs)
            ]);
            $testPdo = null;
            exit;
        }

        // Create database if not exists
        $testPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $testPdo = null;

        if ($action === 'test') {
            echo json_encode(['status' => 'success', 'message' => 'Connection successful!']);
            exit;
        }
    } catch (\Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $e->getMessage()]);
        exit;
    }

    // 2. Save Configuration Block
    if ($action === 'save') {
        $config = [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'pass' => $pass,
            'dbname' => $dbname,
            'app_role' => $app_role
        ];
        
        $userDataPath = getenv('USER_DATA_PATH');
        if ($userDataPath) {
            $configPath = $userDataPath . '/db_config.json';
        } else {
            $configPath = __DIR__ . '/../config/db_config.json';
        }

        if (file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT))) {
            echo json_encode(['status' => 'success', 'message' => 'Configuration saved successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to write configuration file. Please check folder permissions.']);
        }
        exit;
    }
}
