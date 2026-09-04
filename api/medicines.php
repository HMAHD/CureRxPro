<?php
require_once __DIR__ . '/../src/bootstrap.php';
$ctrl = new \CureRx\Controllers\MedicineController(
    new \CureRx\Repositories\MedicineRepository(\CureRx\Core\Database::get())
);
$ctrl->handle(\CureRx\Core\Request::fromGlobals());
