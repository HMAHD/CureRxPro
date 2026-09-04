<?php

namespace CureRx\Repositories;

class TemplateRepository
{
    public function __construct(private \PDO $db) {}

    public function findAll(): array
    {
        $templates = $this->db->query(
            "SELECT t.id, t.name, t.diagnosis,
                    ti.id as item_id, ti.medicine_name, ti.strength,
                    ti.dosage, ti.instruction, ti.duration, ti.quantity
             FROM templates t
             LEFT JOIN template_items ti ON ti.template_id = t.id
             ORDER BY t.id ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        return $this->groupItems($templates);
    }

    private function groupItems(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $tid = $row['id'];
            if (!isset($result[$tid])) {
                $result[$tid] = [
                    'id'        => $row['id'],
                    'name'      => $row['name'],
                    'diagnosis' => $row['diagnosis'],
                    'items'     => [],
                ];
            }
            if ($row['item_id'] !== null) {
                $result[$tid]['items'][] = [
                    'id'            => $row['item_id'],
                    'medicine_name' => $row['medicine_name'],
                    'strength'      => $row['strength'],
                    'dosage'        => $row['dosage'],
                    'instruction'   => $row['instruction'],
                    'duration'      => $row['duration'],
                    'quantity'      => $row['quantity'],
                ];
            }
        }
        return array_values($result);
    }

    public function create(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO templates (name, diagnosis) VALUES (:name, :diagnosis)"
            );
            $stmt->execute(['name' => $data['name'], 'diagnosis' => $data['diagnosis'] ?? null]);
            $tid = (int)$this->db->lastInsertId();

            foreach ($data['items'] ?? [] as $item) {
                $this->db->prepare(
                    "INSERT INTO template_items
                        (template_id, medicine_name, strength, dosage, instruction, duration, quantity)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $tid,
                    $item['medicine_name'] ?? '',
                    $item['strength']      ?? null,
                    $item['dosage']        ?? null,
                    $item['instruction']   ?? null,
                    $item['duration']      ?? null,
                    $item['quantity']      ?? null,
                ]);
            }
            $this->db->commit();
            return $tid;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM templates WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
