<?php
// api/run_migrations.php - Unified Database Migration Engine for CureRxPro
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth_check.php';
verify_api_request();

try {
    // 1. Create schema_migrations tracking table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `migration_file` VARCHAR(255) UNIQUE NOT NULL,
        `executed_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Fetch already executed migrations
    $executed = $pdo->query("SELECT `migration_file` FROM `schema_migrations`")->fetchAll(PDO::FETCH_COLUMN);

    // 2. Scan scripts/migrations/ directory for SQL files
    $migrationDir = __DIR__ . '/../scripts/migrations';
    $files = glob($migrationDir . '/*.sql');
    sort($files);

    $applied = [];

    foreach ($files as $filePath) {
        $fileName = basename($filePath);
        if (!in_array($fileName, $executed)) {
            $sql = file_get_contents($filePath);
            if (!empty(trim($sql))) {
                $pdo->exec($sql);
                $stmt = $pdo->prepare("INSERT INTO `schema_migrations` (`migration_file`) VALUES (?)");
                $stmt->execute([$fileName]);
                $applied[] = $fileName;
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'applied_migrations' => $applied,
        'message' => count($applied) > 0 ? 'Migrations applied successfully.' : 'Database schema up to date.'
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
