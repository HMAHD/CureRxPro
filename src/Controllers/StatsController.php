<?php

namespace CureRx\Controllers;

use CureRx\Core\Request;
use CureRx\Core\Response;

class StatsController
{
    public function __construct(private \PDO $db) {}

    public function handle(Request $req): never
    {
        $doctorId = (int)$req->get('doctor_id', 0);
        $today    = date('Y-m-d');

        // All queries use indexed columns (indexes added in migration 005)
        $todayPatients = $this->db->prepare(
            "SELECT COUNT(*) FROM prescriptions
             WHERE DATE(created_at) = ? AND deleted_at IS NULL"
            . ($doctorId > 0 ? " AND doctor_id = ?" : "")
        );
        $todayPatients->execute($doctorId > 0 ? [$today, $doctorId] : [$today]);

        $todayQueue = $this->db->prepare(
            "SELECT COUNT(*) FROM waiting_queue WHERE DATE(created_at) = ?"
            . ($doctorId > 0 ? " AND doctor_id = ?" : "")
        );
        $todayQueue->execute($doctorId > 0 ? [$today, $doctorId] : [$today]);

        $todayBilling = $this->db->prepare(
            "SELECT COALESCE(SUM(channeling_fee), 0) FROM channeling_bills
             WHERE DATE(created_at) = ?"
            . ($doctorId > 0 ? " AND doctor_id = ?" : "")
        );
        $todayBilling->execute($doctorId > 0 ? [$today, $doctorId] : [$today]);

        Response::json([
            'status' => 'success',
            'data'   => [
                'today_patients' => (int)$todayPatients->fetchColumn(),
                'today_queue'    => (int)$todayQueue->fetchColumn(),
                'today_billing'  => (float)$todayBilling->fetchColumn(),
            ],
        ]);
    }
}
