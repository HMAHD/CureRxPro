<?php
require_once __DIR__ . '/../src/bootstrap.php';
$ctrl = new \CureRx\Controllers\DoctorController(
    new \CureRx\Repositories\DoctorRepository(\CureRx\Core\Database::get())
);
$ctrl->handle(\CureRx\Core\Request::fromGlobals());
