<?php
require_once __DIR__ . '/../src/bootstrap.php';
$db   = \CureRx\Core\Database::get();
$ctrl = new \CureRx\Controllers\QueueController(
    new \CureRx\Services\QueueService($db),
    new \CureRx\Repositories\QueueRepository($db)
);
$ctrl->handle(\CureRx\Core\Request::fromGlobals());
