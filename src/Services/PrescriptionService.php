<?php

namespace CureRx\Services;

use CureRx\Core\AuditLogger;

class PrescriptionService
{
    public function __construct(private \PDO $db) {}

    public function create(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO prescriptions
                    (patient_id, doctor_id, patient_name, patient_age, patient_gender,
                     patient_phone, doctor_name, diagnosis, follow_up_date,
                     consultation_fee, medicines_total, discount, grand_total)
                 VALUES
                    (:patient_id, :doctor_id, :patient_name, :patient_age, :patient_gender,
                     :patient_phone, :doctor_name, :diagnosis, :follow_up_date,
                     :consultation_fee, :medicines_total, :discount, :grand_total)"
            );
            $stmt->execute([
                'patient_id'       => $data['patient_id'],
                'doctor_id'        => $data['doctor_id']        ?? null,
                'patient_name'     => $data['patient_name']     ?? '',
                'patient_age'      => $data['patient_age']      ?? null,
                'patient_gender'   => $data['patient_gender']   ?? null,
                'patient_phone'    => $data['patient_phone']    ?? null,
                'doctor_name'      => $data['doctor_name']      ?? null,
                'diagnosis'        => $data['diagnosis']        ?? null,
                'follow_up_date'   => $data['follow_up_date']   ?? null,
                'consultation_fee' => $data['consultation_fee'] ?? 0,
                'medicines_total'  => $data['medicines_total']  ?? 0,
                'discount'         => $data['discount']         ?? 0,
                'grand_total'      => $data['grand_total']      ?? 0,
            ]);
            $rxId = (int)$this->db->lastInsertId();

            foreach ($data['items'] ?? [] as $item) {
                $this->db->prepare(
                    "INSERT INTO prescription_items
                        (prescription_id, medicine_name, strength, dosage,
                         instruction, duration, quantity, unit_price, item_total, item_source)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $rxId,
                    $item['medicine_name'] ?? '',
                    $item['strength']      ?? null,
                    $item['dosage']        ?? null,
                    $item['instruction']   ?? null,
                    $item['duration']      ?? null,
                    $item['quantity']      ?? null,
                    $item['unit_price']    ?? 0,
                    $item['item_total']    ?? 0,
                    $item['item_source']   ?? 'clinic',
                ]);
            }

            AuditLogger::log($this->db, 'create', 'prescriptions', $rxId, null, $data);
            $this->db->commit();
            return $rxId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
