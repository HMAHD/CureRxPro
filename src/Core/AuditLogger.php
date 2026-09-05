<?php

namespace CureRx\Core;

class AuditLogger
{
    public static function log(
        \PDO $pdo,
        string $action,
        string $table,
        int $recordId,
        ?array $old = null,
        ?array $new = null
    ): void {
        $workstation = $_SERVER['HTTP_X_WORKSTATION'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $stmt = $pdo->prepare(
            "INSERT INTO `audit_log`
                (`action`, `table_name`, `record_id`, `old_values`, `new_values`, `workstation`)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $action,
            $table,
            $recordId,
            $old !== null ? json_encode($old) : null,
            $new !== null ? json_encode($new) : null,
            $workstation,
        ]);
    }
}
