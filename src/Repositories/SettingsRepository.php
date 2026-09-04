<?php

namespace CureRx\Repositories;

class SettingsRepository
{
    public function __construct(private \PDO $db) {}

    public function get(): array
    {
        return $this->db->query("SELECT * FROM settings LIMIT 1")
                        ->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public function update(array $data): void
    {
        $allowed = [
            'clinic_name', 'doctor_name', 'address', 'phone', 'slmc_reg',
            'practice_mode', 'subscription_status', 'subscription_plan',
            'subscription_expires_at', 'license_key', 'active_doctor_id',
            'app_role', 'language',
        ];
        $sets   = [];
        $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]          = "`{$col}` = ?";
                $params[]        = $data[$col];
            }
        }
        if (empty($sets)) {
            return;
        }
        $this->db->prepare("UPDATE settings SET " . implode(', ', $sets) . " WHERE id = 1")
                 ->execute($params);
    }
}
