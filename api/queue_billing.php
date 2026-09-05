<?php
require_once __DIR__ . '/../src/bootstrap.php';
$ctrl = new \CureRx\Controllers\BillingController(
    new \CureRx\Repositories\BillingRepository(\CureRx\Core\Database::get())
);
$ctrl->handle(\CureRx\Core\Request::fromGlobals());
