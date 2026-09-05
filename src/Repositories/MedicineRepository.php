<?php

namespace CureRx\Repositories;

class MedicineRepository
{
    public function __construct(private \PDO $db) {}

    public function search(string $q, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, strength, unit, unit_price, stock_qty, instruction_en, instruction_si
             FROM medicines
             WHERE name LIKE ? OR strength LIKE ?
             ORDER BY name ASC
             LIMIT ? OFFSET ?"
        );
        $like = '%' . $q . '%';
        $stmt->execute([$like, $like, $limit, $offset]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, strength, unit, unit_price, stock_qty, instruction_en, instruction_si
             FROM medicines
             ORDER BY name ASC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function count(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM medicines")->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO medicines (name, strength, unit, unit_price, stock_qty, instruction_en, instruction_si)
             VALUES (:name, :strength, :unit, :unit_price, :stock_qty, :instruction_en, :instruction_si)"
        );
        $stmt->execute([
            'name'           => $data['name'],
            'strength'       => $data['strength']       ?? null,
            'unit'           => $data['unit']           ?? 'Tablet',
            'unit_price'     => $data['unit_price']     ?? 0,
            'stock_qty'      => $data['stock_qty']      ?? 0,
            'instruction_en' => $data['instruction_en'] ?? null,
            'instruction_si' => $data['instruction_si'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE medicines
             SET name=:name, strength=:strength, unit=:unit,
                 unit_price=:unit_price, stock_qty=:stock_qty,
                 instruction_en=:instruction_en, instruction_si=:instruction_si
             WHERE id=:id"
        );
        return $stmt->execute([
            'name'           => $data['name'],
            'strength'       => $data['strength']       ?? null,
            'unit'           => $data['unit']           ?? 'Tablet',
            'unit_price'     => $data['unit_price']     ?? 0,
            'stock_qty'      => $data['stock_qty']      ?? 0,
            'instruction_en' => $data['instruction_en'] ?? null,
            'instruction_si' => $data['instruction_si'] ?? null,
            'id'             => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM medicines WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
