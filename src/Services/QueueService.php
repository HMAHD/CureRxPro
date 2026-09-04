<?php

namespace CureRx\Services;

class QueueService
{
    public function __construct(private \PDO $db) {}

    public function addToQueue(int $doctorId, int $patientId, string $patientName): array
    {
        // SELECT FOR UPDATE and INSERT must be in the same transaction to prevent token races
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(MAX(token_number), 0) + 1
                 FROM waiting_queue
                 WHERE doctor_id = ? AND DATE(created_at) = CURDATE()
                 FOR UPDATE"
            );
            $stmt->execute([$doctorId]);
            $token = (int)$stmt->fetchColumn();

            $stmt = $this->db->prepare(
                "INSERT INTO waiting_queue
                    (doctor_id, patient_id, patient_name, token_number, status)
                 VALUES (?, ?, ?, ?, 'Waiting')"
            );
            $stmt->execute([$doctorId, $patientId, $patientName, $token]);
            $id = (int)$this->db->lastInsertId();
            $this->db->commit();
            return ['id' => $id, 'token_number' => $token];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
