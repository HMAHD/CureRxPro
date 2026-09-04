<?php
require_once __DIR__ . '/../src/bootstrap.php';
$db   = \CureRx\Core\Database::get();
$ctrl = new \CureRx\Controllers\PrescriptionController(
    new \CureRx\Services\PrescriptionService($db),
    new \CureRx\Repositories\PrescriptionRepository($db)
);
$ctrl->handle(\CureRx\Core\Request::fromGlobals());
