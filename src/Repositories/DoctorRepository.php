<?php

namespace CureRx\Repositories;

class DoctorRepository
{
    public function __construct(private \PDO $db) {}

    public function findAll(): array
    {
        return $this->db->query(
            "SELECT id, name, specialization, slmc_reg, phone FROM doctors ORDER BY id ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM doctors WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO doctors (name, specialization, slmc_reg, phone)
             VALUES (:name, :specialization, :slmc_reg, :phone)"
        );
        $stmt->execute([
            'name'           => $data['name'],
            'specialization' => $data['specialization'] ?? null,
            'slmc_reg'       => $data['slmc_reg']       ?? null,
            'phone'          => $data['phone']           ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE doctors SET name=:name, specialization=:specialization,
             slmc_reg=:slmc_reg, phone=:phone WHERE id=:id"
        );
        return $stmt->execute([
            'name'           => $data['name'],
            'specialization' => $data['specialization'] ?? null,
            'slmc_reg'       => $data['slmc_reg']       ?? null,
            'phone'          => $data['phone']           ?? null,
            'id'             => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM doctors WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
