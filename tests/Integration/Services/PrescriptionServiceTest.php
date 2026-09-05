<?php

namespace CureRx\Tests\Integration\Services;

use CureRx\Services\PrescriptionService;
use PHPUnit\Framework\TestCase;

class PrescriptionServiceTest extends TestCase
{
    private static \PDO $db;

    public static function setUpBeforeClass(): void
    {
        static::$db = testDbPdo();
    }

    protected function setUp(): void
    {
        static::$db->exec("DELETE FROM prescription_items");
        static::$db->exec("DELETE FROM prescriptions");
        static::$db->exec("DELETE FROM audit_log");
        static::$db->exec("DELETE FROM patients");
        static::$db->exec(
            "INSERT INTO patients (name, age, gender, patient_code) VALUES ('Test Patient', 30, 'Male', 'P001')"
        );
    }

    /** SQLite happy path: prescription + items + audit_log row are all inserted */
    public function testCreateReturnsPrescriptionId(): void
    {
        $patientId = (int) static::$db->lastInsertId();
        $service   = new PrescriptionService(static::$db);

        $rxId = $service->create([
            'patient_id'       => $patientId,
            'patient_name'     => 'Test Patient',
            'diagnosis'        => 'Common Cold',
            'consultation_fee' => 1000.00,
            'medicines_total'  => 200.00,
            'discount'         => 0,
            'grand_total'      => 1200.00,
            'items'            => [
                [
                    'medicine_name' => 'Paracetamol',
                    'strength'      => '500mg',
                    'dosage'        => '2 tabs tds',
                    'unit_price'    => 10.00,
                    'item_total'    => 60.00,
                    'item_source'   => 'clinic',
                ],
            ],
        ]);

        $this->assertGreaterThan(0, $rxId);

        $rx = static::$db->query("SELECT * FROM prescriptions WHERE id = {$rxId}")->fetch();
        $this->assertSame('Common Cold', $rx['diagnosis']);
        $this->assertSame($patientId, (int) $rx['patient_id']);

        $items = static::$db->query(
            "SELECT * FROM prescription_items WHERE prescription_id = {$rxId}"
        )->fetchAll();
        $this->assertCount(1, $items);
        $this->assertSame('Paracetamol', $items[0]['medicine_name']);

        // AuditLogger::log() must have written one row
        $auditCount = (int) static::$db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
        $this->assertGreaterThan(0, $auditCount);
    }

    /** Mock PDO: rollBack() is called exactly once and the exception propagates */
    public function testCreateRollsBackWhenItemInsertFails(): void
    {
        $callCount = 0;
        $mockStmt  = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturnCallback(
            function () use (&$callCount): bool {
                $callCount++;
                if ($callCount >= 2) {
                    throw new \RuntimeException('Item insert failed');
                }
                return true;
            }
        );

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('beginTransaction')->willReturn(true);
        $mockPdo->method('prepare')->willReturn($mockStmt);
        $mockPdo->method('lastInsertId')->willReturn('1');
        $mockPdo->expects($this->once())->method('rollBack');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Item insert failed');

        $service = new PrescriptionService($mockPdo);
        $service->create([
            'patient_id'   => 1,
            'patient_name' => 'Test',
            'items'        => [['medicine_name' => 'Drug A']],
        ]);
    }
}
