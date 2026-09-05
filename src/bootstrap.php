<?php

// PSR-4 autoloader — no Composer required at runtime
spl_autoload_register(function (string $class): void {
    $prefix = 'CureRx\\';
    $base   = __DIR__ . '/';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = $base . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Wire global PDO into Database singleton
require_once __DIR__ . '/../config/db.php';
/** @var \PDO $pdo */
\CureRx\Core\Database::setConnection($pdo);

// Register error handler
\CureRx\Core\ErrorHandler::register();

// Run pending migrations
(new \CureRx\Core\Migrations())->run(\CureRx\Core\Database::get());
