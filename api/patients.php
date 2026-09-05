<?php
require_once __DIR__ . '/../src/bootstrap.php';
$ctrl = new \CureRx\Controllers\PatientController(
    new \CureRx\Repositories\PatientRepository(\CureRx\Core\Database::get())
);
$ctrl->handle(\CureRx\Core\Request::fromGlobals());
