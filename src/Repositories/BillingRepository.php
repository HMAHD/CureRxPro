<?php

namespace CureRx\Repositories;

class BillingRepository
{
    public function __construct(private \PDO $db) {}

    public function findByDoctor(int $doctorId, string $date): array
    {
        $stmt = $this->db->prepare(
            "SELECT cb.*, p.name as patient_name, p.phone as patient_phone
             FROM channeling_bills cb
             LEFT JOIN patients p ON p.id = cb.patient_id
             WHERE cb.doctor_id = ? AND DATE(cb.created_at) = ?
             ORDER BY cb.token_number ASC"
        );
        $stmt->execute([$doctorId, $date]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO channeling_bills
                (doctor_id, patient_id, patient_name, token_number,
                 channeling_fee, payment_status)
             VALUES (:doctor_id, :patient_id, :patient_name, :token_number,
                     :channeling_fee, :payment_status)"
        );
        $stmt->execute([
            'doctor_id'      => $data['doctor_id'],
            'patient_id'     => $data['patient_id']     ?? null,
            'patient_name'   => $data['patient_name'],
            'token_number'   => $data['token_number'],
            'channeling_fee' => $data['channeling_fee'] ?? 0,
            'payment_status' => $data['payment_status'] ?? 'Unpaid',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updatePayment(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE channeling_bills SET payment_status = ? WHERE id = ?"
        );
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }
}
