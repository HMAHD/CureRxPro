<?php

namespace CureRx\Core;

class Database
{
    private static ?\PDO $connection = null;

    public static function get(): \PDO
    {
        if (self::$connection === null) {
            throw new \RuntimeException('Database connection not initialized.');
        }
        return self::$connection;
    }

    public static function setConnection(\PDO $pdo): void
    {
        self::$connection = $pdo;
    }
}
