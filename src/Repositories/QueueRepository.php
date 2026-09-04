<?php

namespace CureRx\Repositories;

class QueueRepository
{
    public function __construct(private \PDO $db) {}

    public function findActive(int $doctorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT wq.*, p.age as patient_age, p.gender as patient_gender,
                    p.phone as patient_phone, p.patient_code
             FROM waiting_queue wq
             LEFT JOIN patients p ON p.id = wq.patient_id
             WHERE wq.doctor_id = ? AND DATE(wq.created_at) = CURDATE()
               AND wq.status != 'Done'
             ORDER BY wq.token_number ASC"
        );
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE waiting_queue SET status = ? WHERE id = ?"
        );
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    public function remove(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM waiting_queue WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function getTodayStats(int $doctorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT status, COUNT(*) as count
             FROM waiting_queue
             WHERE doctor_id = ? AND DATE(created_at) = CURDATE()
             GROUP BY status"
        );
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
