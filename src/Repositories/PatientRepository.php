<?php

namespace CureRx\Repositories;

class PatientRepository
{
    public function __construct(private \PDO $db) {}

    public function search(string $q): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, phone, age, gender, address, patient_code
             FROM patients
             WHERE deleted_at IS NULL
               AND (name LIKE ? OR phone LIKE ? OR patient_code LIKE ?)
             ORDER BY name ASC
             LIMIT 50"
        );
        $like = '%' . $q . '%';
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM patients WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, phone, age, gender, address, patient_code, created_at
             FROM patients
             WHERE deleted_at IS NULL
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO patients (name, phone, age, gender, address, patient_code)
             VALUES (:name, :phone, :age, :gender, :address, :patient_code)"
        );
        $stmt->execute([
            'name'         => $data['name'],
            'phone'        => $data['phone']        ?? null,
            'age'          => $data['age']           ?? null,
            'gender'       => $data['gender']        ?? null,
            'address'      => $data['address']       ?? null,
            'patient_code' => $data['patient_code']  ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE patients
             SET name = :name, phone = :phone, age = :age,
                 gender = :gender, address = :address
             WHERE id = :id AND deleted_at IS NULL"
        );
        return $stmt->execute([
            'name'    => $data['name'],
            'phone'   => $data['phone']   ?? null,
            'age'     => $data['age']     ?? null,
            'gender'  => $data['gender']  ?? null,
            'address' => $data['address'] ?? null,
            'id'      => $id,
        ]);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE patients SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
