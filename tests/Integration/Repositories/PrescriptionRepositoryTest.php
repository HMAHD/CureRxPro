<?php

namespace CureRx\Tests\Integration\Repositories;

use CureRx\Repositories\PrescriptionRepository;
use CureRx\Services\PrescriptionService;
use PHPUnit\Framework\TestCase;

class PrescriptionRepositoryTest extends TestCase
{
    private static \PDO $db;
    private PrescriptionRepository $repo;
    private PrescriptionService $service;
    private int $patientId;

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
            "INSERT INTO patients (name, age, gender, patient_code) VALUES ('Test Patient', 35, 'Male', 'TP001')"
        );
        $this->patientId = (int)static::$db->lastInsertId();

        $this->repo    = new PrescriptionRepository(static::$db);
        $this->service = new PrescriptionService(static::$db);
    }

    // Test 1: find() by id returns correct data including items
    public function testFindByIdReturnsCorrectDataIncludingItems(): void
    {
        $rxId = $this->service->create([
            'patient_id'       => $this->patientId,
            'patient_name'     => 'Test Patient',
            'diagnosis'        => 'Fever',
            'consultation_fee' => 500.00,
            'medicines_total'  => 200.00,
            'discount'         => 0,
            'grand_total'      => 700.00,
            'items'            => [
                ['medicine_name' => 'Paracetamol', 'strength' => '500mg', 'unit_price' => 10.00, 'item_total' => 50.00, 'item_source' => 'clinic'],
            ],
        ]);

        $rx = $this->repo->find($rxId);

        $this->assertNotNull($rx);
        $this->assertSame($rxId, (int)$rx['id']);
        $this->assertSame('Fever', $rx['diagnosis']);
        $this->assertCount(1, $rx['items']);
        $this->assertSame('Paracetamol', $rx['items'][0]['medicine_name']);
        $this->assertSame('500mg', $rx['items'][0]['strength']);
    }

    // Test 2: findAllForPatient() returns prescriptions for that patient
    public function testFindAllForPatientGroupsItemsUnderPrescription(): void
    {
        $this->service->create([
            'patient_id'       => $this->patientId,
            'patient_name'     => 'Test Patient',
            'diagnosis'        => 'Fever',
            'consultation_fee' => 1000.00,
            'medicines_total'  => 300.00,
            'discount'         => 0,
            'grand_total'      => 1300.00,
            'items'            => [
                ['medicine_name' => 'Cetirizine',  'strength' => '10mg',  'unit_price' => 5.00,  'item_total' => 25.00,  'item_source' => 'clinic'],
                ['medicine_name' => 'Paracetamol', 'strength' => '500mg', 'unit_price' => 10.00, 'item_total' => 60.00, 'item_source' => 'clinic'],
            ],
        ]);

        $prescriptions = $this->repo->findAllForPatient($this->patientId);

        $this->assertCount(1, $prescriptions);
        $rx = reset($prescriptions);
        $this->assertSame('Fever', $rx['diagnosis']);
        $this->assertCount(2, $rx['items']);
        $this->assertSame('Cetirizine',  $rx['items'][0]['medicine_name']);
        $this->assertSame('Paracetamol', $rx['items'][1]['medicine_name']);
    }

    // Test 3: Multiple prescriptions are returned in descending id order (ORDER BY p.id DESC)
    public function testFindAllForPatientReturnsMostRecentFirst(): void
    {
        $this->service->create([
            'patient_id' => $this->patientId, 'patient_name' => 'Test Patient',
            'diagnosis' => 'Old Visit', 'consultation_fee' => 500, 'medicines_total' => 0,
            'discount' => 0, 'grand_total' => 500, 'items' => [],
        ]);
        $this->service->create([
            'patient_id' => $this->patientId, 'patient_name' => 'Test Patient',
            'diagnosis' => 'New Visit', 'consultation_fee' => 800, 'medicines_total' => 0,
            'discount' => 0, 'grand_total' => 800, 'items' => [],
        ]);

        $prescriptions = $this->repo->findAllForPatient($this->patientId);
        $this->assertCount(2, $prescriptions);

        $first = reset($prescriptions);
        $this->assertSame('New Visit', $first['diagnosis']); // ORDER BY p.id DESC
    }

    // Test 4: find() with non-existent id returns null
    public function testFindByNonExistentIdReturnsNull(): void
    {
        $result = $this->repo->find(99999);

        $this->assertNull($result);
    }
}
