<?php
// api/run_migrations.php - Unified Database Migration Engine for CureRxPro
require_once __DIR__ . '/../config/db.php';
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../config/auth_check.php';
    verify_api_request();
}

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
                // Execute each statement separately so a duplicate-index error
                // on one statement does not block the rest of the file.
                // Strip line comments before splitting so leading -- lines don't
                // cause the filter to discard the real ALTER/CREATE statement.
                $stripped = preg_replace('/^\s*--[^\n]*$/m', '', $sql);
                $statements = array_filter(
                    array_map('trim', explode(';', $stripped)),
                    fn($s) => $s !== ''
                );
                foreach ($statements as $statement) {
                    try {
                        $pdo->exec($statement);
                    } catch (\PDOException $e) {
                        // 1060 = duplicate column, 1061 = duplicate key name, 1091 = can't drop non-existent — skip
                        if (!in_array($e->errorInfo[1] ?? 0, [1060, 1061, 1091])) {
                            throw $e;
                        }
                    }
                }
                $stmt = $pdo->prepare("INSERT INTO `schema_migrations` (`migration_file`) VALUES (?)");
                $stmt->execute([$fileName]);
                $applied[] = $fileName;
            }
        }
    }

    if ($isCli) {
        if (count($applied) > 0) {
            echo "Applied: " . implode(', ', $applied) . PHP_EOL;
        } else {
            echo "Database schema is up to date." . PHP_EOL;
        }
    } else {
        echo json_encode([
            'status' => 'success',
            'applied_migrations' => $applied,
            'message' => count($applied) > 0 ? 'Migrations applied successfully.' : 'Database schema up to date.'
        ]);
    }
} catch (\Exception $e) {
    if (!$isCli) { http_response_code(500); }
    $msg = $e->getMessage();
    echo $isCli ? "Error: $msg" . PHP_EOL : json_encode(['status' => 'error', 'message' => $msg]);
}
