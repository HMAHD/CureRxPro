<?php

namespace CureRx\Tests\Integration\Repositories;

use CureRx\Repositories\PatientRepository;
use PHPUnit\Framework\TestCase;

class PatientRepositoryTest extends TestCase
{
    private static \PDO $db;
    private PatientRepository $repo;

    public static function setUpBeforeClass(): void
    {
        static::$db = testDbPdo();
    }

    protected function setUp(): void
    {
        static::$db->exec("DELETE FROM patients");
        $this->repo = new PatientRepository(static::$db);
    }

    /** Test 1: create() inserts a patient and returns a positive integer id */
    public function testCreateReturnsPrimaryKey(): void
    {
        $id = $this->repo->create([
            'name'         => 'Alice Perera',
            'phone'        => '0771234567',
            'age'          => 30,
            'gender'       => 'Female',
            'address'      => '123 Main St',
            'patient_code' => 'P001',
        ]);
        $this->assertGreaterThan(0, $id);
    }

    /** Test 2: find(id) returns the correct record with matching name and patient_code */
    public function testFindReturnsCorrectRecord(): void
    {
        $id      = $this->repo->create([
            'name'         => 'Bob',
            'age'          => 25,
            'gender'       => 'Male',
            'patient_code' => 'P002',
        ]);
        $patient = $this->repo->find($id);
        $this->assertNotNull($patient);
        $this->assertSame('Bob', $patient['name']);
        $this->assertSame('P002', $patient['patient_code']);
    }

    /** Test 3: find() for a non-existent id returns null */
    public function testFindReturnsNullForNonExistent(): void
    {
        $this->assertNull($this->repo->find(99999));
    }

    /** Test 4: findAll() returns list of patients and paginates correctly with limit/offset */
    public function testFindAllReturnsPaginatedResults(): void
    {
        $this->repo->create(['name' => 'C1', 'age' => 20, 'gender' => 'Male', 'patient_code' => 'C1']);
        $this->repo->create(['name' => 'C2', 'age' => 21, 'gender' => 'Male', 'patient_code' => 'C2']);
        $this->repo->create(['name' => 'C3', 'age' => 22, 'gender' => 'Male', 'patient_code' => 'C3']);

        $all = $this->repo->findAll(10, 0);
        $this->assertCount(3, $all);

        $page = $this->repo->findAll(2, 0);
        $this->assertCount(2, $page);

        $offset = $this->repo->findAll(10, 2);
        $this->assertCount(1, $offset);
    }

    /** Test 5: search() matches by partial name */
    public function testSearchMatchesByName(): void
    {
        $this->repo->create(['name' => 'Diana Fernando', 'phone' => '0779999999', 'age' => 28, 'gender' => 'Female', 'patient_code' => 'D1']);
        $this->repo->create(['name' => 'Ernest Silva',   'phone' => '0771111111', 'age' => 40, 'gender' => 'Male',   'patient_code' => 'E1']);

        $results = $this->repo->search('Diana');
        $this->assertCount(1, $results);
        $this->assertSame('Diana Fernando', $results[0]['name']);
    }

    /** Test 6: search() matches by phone number */
    public function testSearchMatchesByPhone(): void
    {
        $this->repo->create(['name' => 'Frank', 'phone' => '0772222222', 'age' => 45, 'gender' => 'Male',   'patient_code' => 'F1']);
        $this->repo->create(['name' => 'Grace', 'phone' => '0773333333', 'age' => 32, 'gender' => 'Female', 'patient_code' => 'G1']);

        $results = $this->repo->search('0772222222');
        $this->assertCount(1, $results);
        $this->assertSame('Frank', $results[0]['name']);
    }

    /** Test 7: search() matches by patient_code */
    public function testSearchMatchesByPatientCode(): void
    {
        $this->repo->create(['name' => 'Henry', 'age' => 50, 'gender' => 'Male', 'patient_code' => 'H001']);

        $results = $this->repo->search('H001');
        $this->assertCount(1, $results);
        $this->assertSame('Henry', $results[0]['name']);
    }

    /** Test 8: update() changes the specified fields; verified via find() */
    public function testUpdateChangesFields(): void
    {
        $id = $this->repo->create(['name' => 'Ivy', 'age' => 20, 'gender' => 'Female', 'patient_code' => 'I1']);
        $this->repo->update($id, ['name' => 'Ivy Updated', 'age' => 21, 'gender' => 'Female', 'phone' => null, 'address' => null]);
        $patient = $this->repo->find($id);
        $this->assertNotNull($patient);
        $this->assertSame('Ivy Updated', $patient['name']);
        $this->assertSame(21, (int)$patient['age']);
    }

    /** Test 9: search() returns an empty array when no patient matches the query */
    public function testSearchReturnsEmptyArrayForNoMatch(): void
    {
        $this->repo->create(['name' => 'Jack', 'age' => 35, 'gender' => 'Male', 'patient_code' => 'J1']);

        $results = $this->repo->search('ZZZNOMATCH');
        $this->assertIsArray($results);
        $this->assertCount(0, $results);
    }
}
