<?php

namespace CureRx\Core;

class Migrations
{
    public function run(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `filename`   VARCHAR(255) NOT NULL,
            `applied_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`filename`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $applied = $pdo->query("SELECT `filename` FROM `schema_migrations`")
                       ->fetchAll(\PDO::FETCH_COLUMN);

        $pattern = __DIR__ . '/../../scripts/migrations/*.sql';
        $files   = glob($pattern);
        if ($files === false) {
            return;
        }
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                continue;
            }
            $pdo->exec($sql);
            $pdo->prepare("INSERT INTO `schema_migrations` (`filename`) VALUES (?)")
                ->execute([$name]);
        }
    }
}
