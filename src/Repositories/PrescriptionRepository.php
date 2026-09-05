<?php

namespace CureRx\Repositories;

class PrescriptionRepository
{
    public function __construct(private \PDO $db) {}

    public function findAllForPatient(int $patientId): array
    {
        // Single JOIN query eliminates N+1 (was: one query per prescription for items)
        $stmt = $this->db->prepare(
            "SELECT p.id, p.patient_id, p.doctor_id, p.diagnosis, p.follow_up_date,
                    p.consultation_fee, p.medicines_total, p.discount, p.grand_total,
                    p.created_at, p.doctor_name, p.patient_name, p.patient_age,
                    p.patient_gender, p.patient_phone,
                    pi.id as item_id, pi.medicine_name, pi.strength, pi.dosage,
                    pi.instruction, pi.duration, pi.quantity, pi.unit_price, pi.item_total
             FROM prescriptions p
             LEFT JOIN prescription_items pi ON pi.prescription_id = p.id
             WHERE p.patient_id = ? AND p.deleted_at IS NULL
             ORDER BY p.id DESC, pi.id ASC"
        );
        $stmt->execute([$patientId]);
        return $this->groupItems($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function groupItems(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $pid = $row['id'];
            if (!isset($result[$pid])) {
                $result[$pid] = [
                    'id'               => $row['id'],
                    'patient_id'       => $row['patient_id'],
                    'doctor_id'        => $row['doctor_id'],
                    'diagnosis'        => $row['diagnosis'],
                    'follow_up_date'   => $row['follow_up_date'],
                    'consultation_fee' => $row['consultation_fee'],
                    'medicines_total'  => $row['medicines_total'],
                    'discount'         => $row['discount'],
                    'grand_total'      => $row['grand_total'],
                    'created_at'       => $row['created_at'],
                    'doctor_name'      => $row['doctor_name'],
                    'patient_name'     => $row['patient_name'],
                    'patient_age'      => $row['patient_age'],
                    'patient_gender'   => $row['patient_gender'],
                    'patient_phone'    => $row['patient_phone'],
                    'items'            => [],
                ];
            }
            if ($row['item_id'] !== null) {
                $result[$pid]['items'][] = [
                    'id'            => $row['item_id'],
                    'medicine_name' => $row['medicine_name'],
                    'strength'      => $row['strength'],
                    'dosage'        => $row['dosage'],
                    'instruction'   => $row['instruction'],
                    'duration'      => $row['duration'],
                    'quantity'      => $row['quantity'],
                    'unit_price'    => $row['unit_price'],
                    'item_total'    => $row['item_total'],
                ];
            }
        }
        return array_values($result);
    }

    public function find(int $id): ?array
    {
        $rows = $this->db->prepare(
            "SELECT p.*, pi.id as item_id, pi.medicine_name, pi.strength, pi.dosage,
                    pi.instruction, pi.duration, pi.quantity, pi.unit_price, pi.item_total
             FROM prescriptions p
             LEFT JOIN prescription_items pi ON pi.prescription_id = p.id
             WHERE p.id = ? AND p.deleted_at IS NULL"
        );
        $rows->execute([$id]);
        $result = $this->groupItems($rows->fetchAll(\PDO::FETCH_ASSOC));
        return $result[0] ?? null;
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE prescriptions SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
