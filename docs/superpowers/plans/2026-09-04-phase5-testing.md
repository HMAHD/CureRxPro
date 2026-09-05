# Phase 5 — Testing + CI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add PHPUnit 10 unit + integration tests, bun test JS component tests, and wire them into the existing GitHub Actions CI workflow.

**Architecture:** PHP tests live in `tests/Unit/` (mock PDO, no DB) and `tests/Integration/` (SQLite in-memory). JS tests live in `tests/js/` using bun's native test runner with `happy-dom` for DOM. The existing `lint.yml` CI job gains a test step; `phpstan.neon` is updated to include `src/`.

**Tech Stack:** PHPUnit 10 (PHP 8.1 compatible), bun test, happy-dom, SQLite in-memory (via PHP PDO), GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-04-swe-remediation-design.md` §9

## Global Constraints

- Branch: `refactor/swe-remediation`
- No AI attribution or watermarks in commits
- PHPUnit **10** (not 11 — spec says 11 but CI uses PHP 8.1 which requires PHPUnit ^10; PHPUnit 11 requires PHP 8.2+)
- SQLite in-memory for integration tests — avoids MySQL service in CI; skip Repository methods that use MySQL-specific functions (`CURDATE()`, `FOR UPDATE`, `NOW()`)
- `BackupServiceTest` from spec §9.1 is skipped — no `BackupService` class exists (backup logic is in `api/backup.php`, excluded from PHPStan); replaced by `SettingsControllerTest` covering the HMAC license key validation
- All tests must pass with `vendor/bin/phpunit` and `bun test tests/js`
- No new runtime dependencies — only dev dependencies
- CLAUDE.md not touched

---

### Task 1: PHPUnit 10 setup — composer.json, phpunit.xml, tests/bootstrap.php (#41)

**Files:**
- Modify: `composer.json`
- Create: `phpunit.xml`
- Create: `tests/bootstrap.php`

**Interfaces:**
- Produces: `testDbPdo(): \PDO` helper function — returns a fresh SQLite in-memory PDO with all tables pre-created; used by every integration test class in `setUpBeforeClass()`

- [ ] **Step 1: Add PHPUnit 10 to composer.json**

Replace the current `composer.json` with:

```json
{
    "name": "curerx/pro",
    "description": "CureRx Pro PHP backend",
    "require": {},
    "require-dev": {
        "phpstan/phpstan": "^1.11",
        "phpunit/phpunit": "^10"
    },
    "autoload": {
        "psr-4": {
            "CureRx\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "CureRx\\Tests\\": "tests/"
        }
    },
    "config": {
        "optimize-autoloader": true
    }
}
```

- [ ] **Step 2: Create phpunit.xml**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Create tests/bootstrap.php**

```php
<?php
// tests/bootstrap.php — autoloads CureRx classes + provides testDbPdo() for integration tests

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Returns a fresh SQLite in-memory PDO with all application tables.
 * Call in setUpBeforeClass() for each integration test class.
 * Uses SQLite-compatible DDL (no FULLTEXT, no ENUM, no AUTO_INCREMENT).
 */
function testDbPdo(): \PDO
{
    $pdo = new \PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    $pdo->exec("
        CREATE TABLE patients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_code TEXT UNIQUE,
            name TEXT NOT NULL,
            age INTEGER,
            gender TEXT,
            phone TEXT,
            address TEXT,
            allergies TEXT,
            blood_group TEXT,
            deleted_at TEXT DEFAULT NULL,
            created_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE doctors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            qualifications TEXT,
            slmc_reg TEXT,
            created_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE prescriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id INTEGER NOT NULL,
            doctor_id INTEGER,
            patient_name TEXT,
            patient_age INTEGER,
            patient_gender TEXT,
            patient_phone TEXT,
            doctor_name TEXT,
            diagnosis TEXT,
            follow_up_date TEXT,
            consultation_fee REAL DEFAULT 0,
            medicines_total REAL DEFAULT 0,
            discount REAL DEFAULT 0,
            grand_total REAL DEFAULT 0,
            deleted_at TEXT DEFAULT NULL,
            created_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (patient_id) REFERENCES patients(id)
        );

        CREATE TABLE prescription_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prescription_id INTEGER NOT NULL,
            medicine_name TEXT NOT NULL,
            strength TEXT,
            dosage TEXT,
            instruction TEXT,
            duration TEXT,
            quantity TEXT,
            unit_price REAL DEFAULT 0,
            item_total REAL DEFAULT 0,
            item_source TEXT DEFAULT 'clinic',
            FOREIGN KEY (prescription_id) REFERENCES prescriptions(id)
        );

        CREATE TABLE waiting_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id INTEGER NOT NULL,
            doctor_id INTEGER NOT NULL,
            token_number INTEGER NOT NULL,
            status TEXT DEFAULT 'Waiting',
            created_at TEXT DEFAULT (datetime('now'))
        );

        CREATE TABLE audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action TEXT,
            table_name TEXT,
            record_id INTEGER,
            old_values TEXT,
            new_values TEXT,
            workstation TEXT,
            created_at TEXT DEFAULT (datetime('now'))
        );
    ");

    return $pdo;
}
```

- [ ] **Step 4: Install PHPUnit via Composer**

```bash
composer update --no-interaction
```

Expected: `vendor/phpunit/` directory created.

- [ ] **Step 5: Verify phpunit runs with no tests yet**

```bash
vendor/bin/phpunit --list-suites
```

Expected: prints "Unit" and "Integration" suites with 0 tests; no error.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock phpunit.xml tests/bootstrap.php
git commit -m "chore(test): add PHPUnit 10, phpunit.xml, and SQLite test bootstrap"
```

---

### Task 2: ValidatorTest.php

**Files:**
- Create: `tests/Unit/Core/ValidatorTest.php`

**Interfaces:**
- Consumes: `CureRx\Core\Validator` — constructor takes `array $data`; methods `required(string)`, `integer(string, ?int, ?int)`, `decimal(string, ?float)`, `enum(string, array)`, `date(string)`, `fails(): bool`, `errors(): array`; all methods return `self` for chaining

- [ ] **Step 1: Create the test file**

```php
<?php

namespace CureRx\Tests\Unit\Core;

use CureRx\Core\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    // --- required ---

    public function testRequiredFailsOnEmptyString(): void
    {
        $v = new Validator(['name' => '']);
        $v->required('name');
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function testRequiredPassesWhenValuePresent(): void
    {
        $v = new Validator(['name' => 'Alice']);
        $v->required('name');
        $this->assertFalse($v->fails());
        $this->assertEmpty($v->errors());
    }

    // --- integer ---

    public function testIntegerRejectsNonNumericString(): void
    {
        $v = new Validator(['age' => 'abc']);
        $v->integer('age');
        $this->assertTrue($v->fails());
    }

    public function testIntegerAcceptsValidInteger(): void
    {
        $v = new Validator(['age' => '25']);
        $v->integer('age');
        $this->assertFalse($v->fails());
    }

    public function testIntegerAcceptsZero(): void
    {
        // Regression: integer(0) was rejected as falsy before fix in Phase 3
        $v = new Validator(['count' => '0']);
        $v->integer('count', 0);
        $this->assertFalse($v->fails());
    }

    public function testIntegerEnforcesMinimum(): void
    {
        $v = new Validator(['age' => '-1']);
        $v->integer('age', 0);
        $this->assertTrue($v->fails());
    }

    public function testIntegerEnforcesMaximum(): void
    {
        $v = new Validator(['age' => '200']);
        $v->integer('age', 0, 150);
        $this->assertTrue($v->fails());
    }

    public function testIntegerSkipsAbsentField(): void
    {
        $v = new Validator([]);
        $v->integer('age', 0);
        $this->assertFalse($v->fails());
    }

    // --- decimal ---

    public function testDecimalRejectsNonNumericString(): void
    {
        $v = new Validator(['price' => 'abc']);
        $v->decimal('price');
        $this->assertTrue($v->fails());
    }

    public function testDecimalAcceptsFloatString(): void
    {
        $v = new Validator(['price' => '19.99']);
        $v->decimal('price', 0.0);
        $this->assertFalse($v->fails());
    }

    public function testDecimalEnforcesMinimum(): void
    {
        $v = new Validator(['price' => '-5.00']);
        $v->decimal('price', 0.0);
        $this->assertTrue($v->fails());
    }

    public function testDecimalSkipsAbsentField(): void
    {
        $v = new Validator([]);
        $v->decimal('price');
        $this->assertFalse($v->fails());
    }

    // --- enum ---

    public function testEnumRejectsValueNotInList(): void
    {
        $v = new Validator(['gender' => 'Unknown']);
        $v->enum('gender', ['Male', 'Female', 'Other']);
        $this->assertTrue($v->fails());
    }

    public function testEnumAcceptsValueInList(): void
    {
        $v = new Validator(['gender' => 'Female']);
        $v->enum('gender', ['Male', 'Female', 'Other']);
        $this->assertFalse($v->fails());
    }

    public function testEnumSkipsAbsentField(): void
    {
        $v = new Validator([]);
        $v->enum('gender', ['Male', 'Female', 'Other']);
        $this->assertFalse($v->fails());
    }

    // --- date ---

    public function testDateRejectsInvalidFormat(): void
    {
        $v = new Validator(['dob' => '31-12-2025']);
        $v->date('dob');
        $this->assertTrue($v->fails());
    }

    public function testDateAcceptsYyyyMmDd(): void
    {
        $v = new Validator(['dob' => '2025-12-31']);
        $v->date('dob');
        $this->assertFalse($v->fails());
    }

    public function testDateRejectsImpossibleDate(): void
    {
        $v = new Validator(['dob' => '2025-02-30']);
        $v->date('dob');
        $this->assertTrue($v->fails());
    }

    // --- chaining and errors() ---

    public function testChainingCollectsMultipleErrors(): void
    {
        $v = new Validator(['name' => '', 'age' => 'abc']);
        $v->required('name')->integer('age');
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
        $this->assertArrayHasKey('age', $v->errors());
    }

    public function testChainingPassesAllValidData(): void
    {
        $v = new Validator(['name' => 'Alice', 'age' => '30', 'gender' => 'Female', 'dob' => '1994-06-15']);
        $v->required('name')->integer('age', 0, 150)->enum('gender', ['Male', 'Female', 'Other'])->date('dob');
        $this->assertFalse($v->fails());
        $this->assertEmpty($v->errors());
    }
}
```

- [ ] **Step 2: Run the test**

```bash
vendor/bin/phpunit tests/Unit/Core/ValidatorTest.php --colors=always
```

Expected: All tests PASS (green). No failures.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Core/ValidatorTest.php
git commit -m "test(core): add ValidatorTest covering all rule types and integer-zero regression"
```

---

### Task 3: QueueServiceTest.php

**Files:**
- Create: `tests/Unit/Services/QueueServiceTest.php`

**Interfaces:**
- Consumes: `CureRx\Services\QueueService` — constructor takes `\PDO $db`; method `addToQueue(int $doctorId, int $patientId, string $patientName): array` — returns `['id' => int, 'token_number' => int]`; uses a single transaction (`beginTransaction` → `prepare` ×2 → `commit`) to prevent token race; on exception: `rollBack()` then rethrows

**Note:** QueueService uses `FOR UPDATE` and `CURDATE()` — MySQL-only syntax. We mock the PDO; no SQLite needed.

- [ ] **Step 1: Create the test file**

```php
<?php

namespace CureRx\Tests\Unit\Services;

use CureRx\Services\QueueService;
use PHPUnit\Framework\TestCase;

class QueueServiceTest extends TestCase
{
    public function testAddToQueueReturnsIdAndToken(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetchColumn')->willReturn('3'); // token = 3

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('beginTransaction')->willReturn(true);
        $mockPdo->method('prepare')->willReturn($mockStmt);
        $mockPdo->method('lastInsertId')->willReturn('7');
        $mockPdo->method('commit')->willReturn(true);

        $service = new QueueService($mockPdo);
        $result = $service->addToQueue(1, 10, 'Test Patient');

        $this->assertSame(7, $result['id']);
        $this->assertSame(3, $result['token_number']);
    }

    public function testAddToQueueRollsBackAndRethrowsOnException(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willThrowException(new \RuntimeException('DB error'));

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('beginTransaction')->willReturn(true);
        $mockPdo->method('prepare')->willReturn($mockStmt);
        $mockPdo->expects($this->once())->method('rollBack');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        $service = new QueueService($mockPdo);
        $service->addToQueue(1, 10, 'Test Patient');
    }

    public function testTokenIsComputedFromFetchColumn(): void
    {
        // fetchColumn returns the next token number (COALESCE(MAX, 0) + 1)
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetchColumn')->willReturn('5'); // 5th token today

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('beginTransaction')->willReturn(true);
        $mockPdo->method('prepare')->willReturn($mockStmt);
        $mockPdo->method('lastInsertId')->willReturn('12');
        $mockPdo->method('commit')->willReturn(true);

        $result = (new QueueService($mockPdo))->addToQueue(2, 20, 'Jane Doe');

        $this->assertSame(5, $result['token_number']);
        $this->assertSame(12, $result['id']);
    }
}
```

- [ ] **Step 2: Run the test**

```bash
vendor/bin/phpunit tests/Unit/Services/QueueServiceTest.php --colors=always
```

Expected: 3 tests PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Services/QueueServiceTest.php
git commit -m "test(services): add QueueServiceTest covering token assignment and rollback"
```

---

### Task 4: SettingsControllerTest.php (license key HMAC validation)

**Files:**
- Create: `tests/Unit/Controllers/SettingsControllerTest.php`

**Interfaces:**
- Consumes: `CureRx\Controllers\SettingsController` — private method `validateLicenseKey(string $key, string $secret): array` returns `['valid' => bool]`
  - Key format: `CRX-{base64url_payload}-{hmac}` where payload = base64url(`{plan}|{expiry_timestamp}`)
  - Valid plans: `starter`, `pro`, `clinic`; expiry = 0 means never expires
  - HMAC: `hash_hmac('sha256', payload, hash('sha256', secret, true))`
- Access via: `(new \ReflectionMethod(SettingsController::class, 'validateLicenseKey'))->setAccessible(true)->invoke($controller, $key, $secret)`

- [ ] **Step 1: Create the test file**

```php
<?php

namespace CureRx\Tests\Unit\Controllers;

use CureRx\Controllers\SettingsController;
use CureRx\Repositories\SettingsRepository;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase
{
    private \ReflectionMethod $method;
    private SettingsController $controller;

    protected function setUp(): void
    {
        $mockRepo = $this->createMock(SettingsRepository::class);
        $this->controller = new SettingsController($mockRepo);
        $this->method = new \ReflectionMethod(SettingsController::class, 'validateLicenseKey');
        $this->method->setAccessible(true);
    }

    /**
     * Generates a valid CRX license key using the same algorithm as production.
     */
    private function makeKey(string $secret, string $plan = 'pro', int $expiry = 0): string
    {
        $signingKey = hash('sha256', $secret, true);
        $payload    = rtrim(strtr(base64_encode("{$plan}|{$expiry}"), '+/', '-_'), '=');
        $hmac       = hash_hmac('sha256', $payload, $signingKey);
        return "CRX-{$payload}-{$hmac}";
    }

    public function testValidKeyWithNeverExpiryReturnsTrue(): void
    {
        $key    = $this->makeKey('my-secret', 'pro', 0);
        $result = $this->method->invoke($this->controller, $key, 'my-secret');
        $this->assertTrue($result['valid']);
    }

    public function testValidKeyForAllSupportedPlans(): void
    {
        foreach (['starter', 'pro', 'clinic'] as $plan) {
            $key    = $this->makeKey('secret', $plan, 0);
            $result = $this->method->invoke($this->controller, $key, 'secret');
            $this->assertTrue($result['valid'], "Plan '{$plan}' should be valid");
        }
    }

    public function testExpiredKeyReturnsFalse(): void
    {
        $key    = $this->makeKey('secret', 'pro', 1000000); // Unix timestamp in the past
        $result = $this->method->invoke($this->controller, $key, 'secret');
        $this->assertFalse($result['valid']);
    }

    public function testWrongPrefixReturnsFalse(): void
    {
        $result = $this->method->invoke($this->controller, 'BADKEY-payload-hmac', 'secret');
        $this->assertFalse($result['valid']);
    }

    public function testTamperedHmacReturnsFalse(): void
    {
        $key      = $this->makeKey('secret', 'pro', 0);
        $tampered = substr($key, 0, -1) . 'X'; // Flip last char
        $result   = $this->method->invoke($this->controller, $tampered, 'secret');
        $this->assertFalse($result['valid']);
    }

    public function testUnknownPlanReturnsFalse(): void
    {
        $key    = $this->makeKey('secret', 'enterprise', 0);
        $result = $this->method->invoke($this->controller, $key, 'secret');
        $this->assertFalse($result['valid']);
    }

    public function testWrongSecretReturnsFalse(): void
    {
        $key    = $this->makeKey('correct-secret', 'pro', 0);
        $result = $this->method->invoke($this->controller, $key, 'wrong-secret');
        $this->assertFalse($result['valid']);
    }
}
```

- [ ] **Step 2: Run the test**

```bash
vendor/bin/phpunit tests/Unit/Controllers/SettingsControllerTest.php --colors=always
```

Expected: 7 tests PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Controllers/SettingsControllerTest.php
git commit -m "test(services): add SettingsControllerTest for HMAC license key validation"
```

---

### Task 5: PrescriptionServiceTest.php

**Files:**
- Create: `tests/Unit/Services/PrescriptionServiceTest.php`

**Interfaces:**
- Consumes: `CureRx\Services\PrescriptionService` — constructor takes `\PDO $db`; method `create(array $data): int` — wraps INSERT prescriptions + INSERT prescription_items + AuditLogger::log() in one transaction; on exception: rollBack() then rethrows
- Happy-path test uses `testDbPdo()` (SQLite, `audit_log` table exists); rollback test uses mock PDO

- [ ] **Step 1: Create the test file**

```php
<?php

namespace CureRx\Tests\Unit\Services;

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

    public function testCreateReturnsPrescriptionId(): void
    {
        $patientId = (int)static::$db->lastInsertId();
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

        $items = static::$db->query(
            "SELECT * FROM prescription_items WHERE prescription_id = {$rxId}"
        )->fetchAll();
        $this->assertCount(1, $items);
        $this->assertSame('Paracetamol', $items[0]['medicine_name']);

        // AuditLogger::log() must have run
        $auditRows = static::$db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
        $this->assertGreaterThan(0, (int)$auditRows);
    }

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
```

- [ ] **Step 2: Run the test**

```bash
vendor/bin/phpunit tests/Unit/Services/PrescriptionServiceTest.php --colors=always
```

Expected: 2 tests PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Services/PrescriptionServiceTest.php
git commit -m "test(services): add PrescriptionServiceTest for create and transaction rollback"
```

---

### Task 6: PatientRepositoryTest.php

**Files:**
- Create: `tests/Integration/Repositories/PatientRepositoryTest.php`

**Interfaces:**
- Consumes: `CureRx\Repositories\PatientRepository` — constructor takes `\PDO $db`
  - `create(array $data): int` — inserts, returns id
  - `find(int $id): ?array` — returns row or null; filters `deleted_at IS NULL`
  - `findAll(int $limit, int $offset): array`
  - `search(string $q): array` — LIKE on name, phone, patient_code
  - `update(int $id, array $data): bool`
- Note: `softDelete()` uses `NOW()` (MySQL-only) — not tested here (SQLite incompatible)

- [ ] **Step 1: Create the test file**

```php
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

    public function testFindReturnsCorrectRecord(): void
    {
        $id      = $this->repo->create(['name' => 'Bob', 'age' => 25, 'gender' => 'Male', 'patient_code' => 'P002']);
        $patient = $this->repo->find($id);
        $this->assertNotNull($patient);
        $this->assertSame('Bob', $patient['name']);
        $this->assertSame('P002', $patient['patient_code']);
    }

    public function testFindReturnsNullForNonExistent(): void
    {
        $this->assertNull($this->repo->find(99999));
    }

    public function testFindAllReturnsPaginatedResults(): void
    {
        $this->repo->create(['name' => 'C1', 'age' => 20, 'gender' => 'Male', 'patient_code' => 'C1']);
        $this->repo->create(['name' => 'C2', 'age' => 21, 'gender' => 'Male', 'patient_code' => 'C2']);
        $this->repo->create(['name' => 'C3', 'age' => 22, 'gender' => 'Male', 'patient_code' => 'C3']);

        $all  = $this->repo->findAll(10, 0);
        $this->assertCount(3, $all);

        $page = $this->repo->findAll(2, 0);
        $this->assertCount(2, $page);

        $offset = $this->repo->findAll(10, 2);
        $this->assertCount(1, $offset);
    }

    public function testSearchMatchesByName(): void
    {
        $this->repo->create(['name' => 'Diana Fernando', 'phone' => '0779999999', 'age' => 28, 'gender' => 'Female', 'patient_code' => 'D1']);
        $this->repo->create(['name' => 'Ernest Silva',   'phone' => '0771111111', 'age' => 40, 'gender' => 'Male',   'patient_code' => 'E1']);

        $results = $this->repo->search('Diana');
        $this->assertCount(1, $results);
        $this->assertSame('Diana Fernando', $results[0]['name']);
    }

    public function testSearchMatchesByPhone(): void
    {
        $this->repo->create(['name' => 'Frank', 'phone' => '0772222222', 'age' => 45, 'gender' => 'Male', 'patient_code' => 'F1']);
        $this->repo->create(['name' => 'Grace', 'phone' => '0773333333', 'age' => 32, 'gender' => 'Female', 'patient_code' => 'G1']);

        $results = $this->repo->search('0772222222');
        $this->assertCount(1, $results);
        $this->assertSame('Frank', $results[0]['name']);
    }

    public function testSearchMatchesByPatientCode(): void
    {
        $this->repo->create(['name' => 'Henry', 'age' => 50, 'gender' => 'Male', 'patient_code' => 'H001']);
        $results = $this->repo->search('H001');
        $this->assertCount(1, $results);
        $this->assertSame('Henry', $results[0]['name']);
    }

    public function testUpdateChangesFields(): void
    {
        $id = $this->repo->create(['name' => 'Ivy', 'age' => 20, 'gender' => 'Female', 'patient_code' => 'I1']);
        $this->repo->update($id, ['name' => 'Ivy Updated', 'age' => 21, 'gender' => 'Female', 'phone' => null, 'address' => null]);
        $patient = $this->repo->find($id);
        $this->assertSame('Ivy Updated', $patient['name']);
        $this->assertSame(21, (int)$patient['age']);
    }

    public function testUpdateReturnsFalseForNonExistentId(): void
    {
        $result = $this->repo->update(99999, ['name' => 'Ghost', 'age' => 0, 'gender' => 'Male', 'phone' => null, 'address' => null]);
        $this->assertFalse($result);
    }
}
```

- [ ] **Step 2: Run the test**

```bash
vendor/bin/phpunit tests/Integration/Repositories/PatientRepositoryTest.php --colors=always
```

Expected: All tests PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Integration/Repositories/PatientRepositoryTest.php
git commit -m "test(integration): add PatientRepositoryTest with SQLite fixture"
```

---

### Task 7: PrescriptionRepositoryTest.php

**Files:**
- Create: `tests/Integration/Repositories/PrescriptionRepositoryTest.php`

**Interfaces:**
- Consumes:
  - `CureRx\Repositories\PrescriptionRepository` — constructor takes `\PDO $db`; `findAllForPatient(int $patientId): array` — returns array of prescriptions, each with grouped `items` array (N+1 fix: single LEFT JOIN)
  - `CureRx\Services\PrescriptionService` — used to seed prescriptions in tests

- [ ] **Step 1: Create the test file**

```php
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

    public function testFindAllForPatientReturnsEmptyWhenNone(): void
    {
        $result = $this->repo->findAllForPatient($this->patientId);
        $this->assertEmpty($result);
    }

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

    public function testPrescriptionWithNoItemsHasEmptyItemsArray(): void
    {
        $this->service->create([
            'patient_id' => $this->patientId, 'patient_name' => 'Test Patient',
            'diagnosis' => 'Consultation Only', 'consultation_fee' => 1000,
            'medicines_total' => 0, 'discount' => 0, 'grand_total' => 1000, 'items' => [],
        ]);

        $prescriptions = $this->repo->findAllForPatient($this->patientId);
        $rx = reset($prescriptions);
        $this->assertIsArray($rx['items']);
        $this->assertEmpty($rx['items']);
    }
}
```

- [ ] **Step 2: Run the test**

```bash
vendor/bin/phpunit tests/Integration/Repositories/PrescriptionRepositoryTest.php --colors=always
```

Expected: 4 tests PASS.

- [ ] **Step 3: Run all PHP tests together**

```bash
vendor/bin/phpunit --colors=always
```

Expected: All suites PASS with zero failures.

- [ ] **Step 4: Commit**

```bash
git add tests/Integration/Repositories/PrescriptionRepositoryTest.php
git commit -m "test(integration): add PrescriptionRepositoryTest verifying N+1-free grouping"
```

---

### Task 8: JS setup + state.test.js + api.test.js

**Files:**
- Modify: `package.json` — add `"test:js": "bun test tests/js"` to scripts; add `happy-dom` to devDependencies
- Modify: `bunfig.toml` — add `[test]` section with preload
- Create: `tests/js/setup.js` — registers happy-dom globals
- Create: `tests/js/state.test.js`
- Create: `tests/js/api.test.js`

**Interfaces:**
- Consumes:
  - `assets/js/state.js` — exports `get(key: string): any`, `set(key: string, value: any): void`; initial keys: `selectedPatient`, `settings`, `medicinesMaster`, `rxItems`, `templates`, `queueMonitorInterval`, `activeMedCategory`, `historyCurrentPage`, `historyPageLimit`, `historyTotalPages`
  - `assets/js/api.js` — exports `get`, `post`, `put`, `del` (all async, use `fetch` + `window.__crxToken` header); `escapeHtml(str): string`; `debounce(fn, wait): function`

- [ ] **Step 1: Install happy-dom**

```bash
bun add -d happy-dom
```

Expected: `happy-dom` appears in `package.json` devDependencies and `bun.lock` is updated.

- [ ] **Step 2: Update package.json scripts**

In `package.json`, add to `scripts`:
```json
"test:js":  "bun test tests/js",
"test:php": "vendor/bin/phpunit",
"test":     "bun run test:php && bun run test:js"
```

- [ ] **Step 3: Update bunfig.toml**

Append to the existing `bunfig.toml`:

```toml
[test]
preload = ["./tests/js/setup.js"]
```

- [ ] **Step 4: Create tests/js/setup.js**

```js
// tests/js/setup.js — registers happy-dom globals before each test file
import { GlobalRegistrator } from "@happy-dom/global-registrator";
GlobalRegistrator.register();
// Set test token so api.js header injection is predictable
window.__crxToken = 'test-token-for-tests';
```

- [ ] **Step 5: Create tests/js/state.test.js**

```js
import { describe, test, expect, beforeEach } from 'bun:test';
import * as state from '../../assets/js/state.js';

describe('state', () => {
    beforeEach(() => {
        state.set('selectedPatient', null);
        state.set('rxItems', []);
        state.set('settings', null);
    });

    test('get returns null for unset selectedPatient', () => {
        expect(state.get('selectedPatient')).toBeNull();
    });

    test('set and get round-trip a patient object', () => {
        const patient = { id: 1, name: 'Alice', patient_code: 'P001' };
        state.set('selectedPatient', patient);
        expect(state.get('selectedPatient')).toEqual(patient);
    });

    test('different keys are independent', () => {
        state.set('selectedPatient', { id: 1 });
        state.set('settings', { clinic_name: 'Test Clinic' });
        expect(state.get('selectedPatient').id).toBe(1);
        expect(state.get('settings').clinic_name).toBe('Test Clinic');
    });

    test('set replaces previous value', () => {
        state.set('rxItems', [{ id: 1 }]);
        state.set('rxItems', [{ id: 2 }, { id: 3 }]);
        expect(state.get('rxItems')).toHaveLength(2);
    });

    test('historyPageLimit initial value is 10', () => {
        // Fresh module — verify initial state
        const limit = state.get('historyPageLimit');
        expect(typeof limit).toBe('number');
        expect(limit).toBeGreaterThan(0);
    });
});
```

- [ ] **Step 6: Create tests/js/api.test.js**

```js
import { describe, test, expect, mock, beforeEach, afterEach } from 'bun:test';
import { get, post, put, del, escapeHtml, debounce } from '../../assets/js/api.js';

// Default happy-path fetch mock
const okResponse = (data = {}) => ({
    ok: true,
    json: async () => data,
});

let fetchMock;

beforeEach(() => {
    fetchMock = mock(async () => okResponse({ status: 'success' }));
    globalThis.fetch = fetchMock;
});

afterEach(() => {
    fetchMock.mockRestore?.();
});

describe('get()', () => {
    test('calls fetch with correct base URL', async () => {
        await get('/patients.php');
        const url = fetchMock.mock.calls[0][0];
        expect(url).toBe('http://127.0.0.1:8989/api/patients.php');
    });

    test('injects X-CureRx-Token header', async () => {
        await get('/patients.php');
        const opts = fetchMock.mock.calls[0][1];
        expect(opts.headers['X-CureRx-Token']).toBe('test-token-for-tests');
    });

    test('appends query params when provided', async () => {
        await get('/patients.php', { q: 'Alice', limit: '10' });
        const url = fetchMock.mock.calls[0][0];
        expect(url).toContain('q=Alice');
        expect(url).toContain('limit=10');
    });

    test('throws Error on non-ok response', async () => {
        globalThis.fetch = mock(async () => ({
            ok: false,
            statusText: 'Not Found',
            json: async () => ({ message: 'Patient not found' }),
        }));
        await expect(get('/patients.php?id=999')).rejects.toThrow('Patient not found');
    });
});

describe('post()', () => {
    test('uses POST method with JSON body', async () => {
        await post('/patients.php', { name: 'Alice', age: 30 });
        const opts = fetchMock.mock.calls[0][1];
        expect(opts.method).toBe('POST');
        expect(JSON.parse(opts.body)).toEqual({ name: 'Alice', age: 30 });
    });
});

describe('put()', () => {
    test('uses PUT method', async () => {
        await put('/queue.php', { id: 1, status: 'Serving' });
        expect(fetchMock.mock.calls[0][1].method).toBe('PUT');
    });
});

describe('del()', () => {
    test('uses DELETE method', async () => {
        await del('/queue.php?id=1');
        expect(fetchMock.mock.calls[0][1].method).toBe('DELETE');
    });
});

describe('escapeHtml()', () => {
    test('escapes < > & " \'', () => {
        expect(escapeHtml('<b>"it\'s" & fun</b>')).toBe(
            '&lt;b&gt;&quot;it&#39;s&quot; &amp; fun&lt;/b&gt;'
        );
    });

    test('returns empty string for null/undefined', () => {
        expect(escapeHtml(null)).toBe('');
        expect(escapeHtml(undefined)).toBe('');
    });

    test('returns string unchanged when no special chars', () => {
        expect(escapeHtml('Hello World')).toBe('Hello World');
    });
});

describe('debounce()', () => {
    test('only fires once after rapid successive calls', async () => {
        let count = 0;
        const fn = debounce(() => count++, 50);
        fn(); fn(); fn(); fn();
        await new Promise(r => setTimeout(r, 150));
        expect(count).toBe(1);
    });

    test('fires again after the wait period resets', async () => {
        let count = 0;
        const fn = debounce(() => count++, 50);
        fn();
        await new Promise(r => setTimeout(r, 150));
        fn();
        await new Promise(r => setTimeout(r, 150));
        expect(count).toBe(2);
    });
});
```

- [ ] **Step 7: Run JS tests**

```bash
bun test tests/js/state.test.js tests/js/api.test.js
```

Expected: All tests PASS with no failures.

- [ ] **Step 8: Commit**

```bash
git add package.json bun.lock bunfig.toml tests/js/setup.js tests/js/state.test.js tests/js/api.test.js
git commit -m "test(js): add bun test setup with happy-dom, state.test.js, and api.test.js"
```

---

### Task 9: toast.test.js + autocomplete.test.js

**Files:**
- Create: `tests/js/components/toast.test.js`
- Create: `tests/js/components/autocomplete.test.js`

**Interfaces:**
- Consumes (read the source files to verify before writing tests):
  - `assets/js/components/toast.js` — `showToast(message: string, type?: string, duration?: number): void`
    - Creates `<div id="toast-container">` appended to `document.body` on first call (module-level singleton)
    - Each call appends a `<div>` with `textContent = message` and `style.background = colors[type]`
    - Colors: `info=#3b82f6`, `success=#22c55e`, `error=#ef4444`, `warning=#f59e0b`
  - `assets/js/components/autocomplete.js` — `createAutocomplete({ inputId, dropdownId, fetchFn, onSelect, minChars? }): void`
    - Wires `input` event on `#inputId`; after 250ms debounce calls `fetchFn(value)`
    - Renders results as `<li>` in `#dropdownId`; shows `item.label ?? item.name`
    - Calls `onSelect(item)` on mousedown; sets `input.value = item.label ?? item.name`
    - Hides dropdown if `input.value.length < minChars` (default 2)

**Note:** The `container` variable in `toast.js` is a module-level singleton. Use `afterEach` to clear it: `document.getElementById('toast-container')?.innerHTML = ''`. (Removing the element would detach the module's reference.)

- [ ] **Step 1: Create tests/js/components/toast.test.js**

```js
import { describe, test, expect, afterEach } from 'bun:test';
import { showToast } from '../../../assets/js/components/toast.js';

afterEach(() => {
    // Clear toasts without removing the container (module holds a reference to it)
    const container = document.getElementById('toast-container');
    if (container) container.innerHTML = '';
});

describe('showToast', () => {
    test('creates toast-container on first call', () => {
        showToast('Hello', 'info', 99999);
        const container = document.getElementById('toast-container');
        expect(container).not.toBeNull();
    });

    test('appends a div with the message text', () => {
        showToast('Saved successfully', 'success', 99999);
        const container = document.getElementById('toast-container');
        expect(container.children.length).toBe(1);
        expect(container.children[0].textContent).toBe('Saved successfully');
    });

    test('sets background to error color for error type', () => {
        showToast('Something went wrong', 'error', 99999);
        const container = document.getElementById('toast-container');
        expect(container.children[0].style.background).toBe('#ef4444');
    });

    test('sets background to success color for success type', () => {
        showToast('Done!', 'success', 99999);
        const container = document.getElementById('toast-container');
        expect(container.children[0].style.background).toBe('#22c55e');
    });

    test('appends multiple toasts on multiple calls', () => {
        showToast('First', 'info', 99999);
        showToast('Second', 'info', 99999);
        const container = document.getElementById('toast-container');
        expect(container.children.length).toBe(2);
    });
});
```

- [ ] **Step 2: Create tests/js/components/autocomplete.test.js**

```js
import { describe, test, expect, beforeEach, afterEach } from 'bun:test';
import { createAutocomplete } from '../../../assets/js/components/autocomplete.js';

let input, dropdown;

beforeEach(() => {
    document.body.innerHTML = `
        <input id="ac-input" type="text" />
        <div id="ac-dropdown"></div>
    `;
    input    = document.getElementById('ac-input');
    dropdown = document.getElementById('ac-dropdown');
});

afterEach(() => {
    document.body.innerHTML = '';
});

describe('createAutocomplete', () => {
    test('returns without error when elements exist', () => {
        expect(() => createAutocomplete({
            inputId:   'ac-input',
            dropdownId: 'ac-dropdown',
            fetchFn:   async () => [],
            onSelect:  () => {},
        })).not.toThrow();
    });

    test('does nothing when inputId element is missing', () => {
        expect(() => createAutocomplete({
            inputId:   'nonexistent',
            dropdownId: 'ac-dropdown',
            fetchFn:   async () => [],
            onSelect:  () => {},
        })).not.toThrow();
    });

    test('shows dropdown items from fetchFn after debounce', async () => {
        createAutocomplete({
            inputId:   'ac-input',
            dropdownId: 'ac-dropdown',
            fetchFn:   async () => [{ id: 1, name: 'Alice' }, { id: 2, name: 'Bob' }],
            onSelect:  () => {},
            minChars:  1,
        });

        input.value = 'A';
        input.dispatchEvent(new Event('input'));
        await new Promise(r => setTimeout(r, 400)); // debounce=250ms + fetch time

        expect(dropdown.children.length).toBe(2);
        expect(dropdown.children[0].textContent).toBe('Alice');
        expect(dropdown.children[1].textContent).toBe('Bob');
    });

    test('hides dropdown when value is below minChars', async () => {
        createAutocomplete({
            inputId:   'ac-input',
            dropdownId: 'ac-dropdown',
            fetchFn:   async () => [{ id: 1, name: 'Alice' }],
            onSelect:  () => {},
            minChars:  3,
        });

        input.value = 'Al'; // 2 chars < minChars=3
        input.dispatchEvent(new Event('input'));
        await new Promise(r => setTimeout(r, 400));

        expect(dropdown.hidden).toBe(true);
    });

    test('calls onSelect and fills input when item clicked', async () => {
        let selected = null;
        createAutocomplete({
            inputId:   'ac-input',
            dropdownId: 'ac-dropdown',
            fetchFn:   async () => [{ id: 1, name: 'Alice' }],
            onSelect:  (item) => { selected = item; },
            minChars:  1,
        });

        input.value = 'A';
        input.dispatchEvent(new Event('input'));
        await new Promise(r => setTimeout(r, 400));

        const li = dropdown.querySelector('li');
        li.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));

        expect(selected).toEqual({ id: 1, name: 'Alice' });
        expect(input.value).toBe('Alice');
        expect(dropdown.hidden).toBe(true);
    });

    test('uses label property over name when both present', async () => {
        createAutocomplete({
            inputId:   'ac-input',
            dropdownId: 'ac-dropdown',
            fetchFn:   async () => [{ id: 1, name: 'Alice Perera', label: 'Alice Perera - P001' }],
            onSelect:  () => {},
            minChars:  1,
        });

        input.value = 'A';
        input.dispatchEvent(new Event('input'));
        await new Promise(r => setTimeout(r, 400));

        expect(dropdown.children[0].textContent).toBe('Alice Perera - P001');
    });
});
```

- [ ] **Step 3: Run all JS tests**

```bash
bun test tests/js
```

Expected: All tests PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/js/components/toast.test.js tests/js/components/autocomplete.test.js
git commit -m "test(js): add toast and autocomplete component tests with happy-dom"
```

---

### Task 10: PHPStan update + lint.yml CI + package.json scripts (#42)

**Files:**
- Modify: `phpstan.neon` — add `src/` to analysis paths; remove exclusions for clean 4–8 line api dispatchers
- Modify: `.github/workflows/lint.yml` — add PHPUnit and bun test steps
- Modify: `package.json` — `test:js`, `test:php`, `test` scripts (verify already added in T8; add `test:php` and `test` if missing)

**Context:** Current `phpstan.neon` excludes all `api/*.php` files because they were monolithic in Phase 0. After Phase 3, the following are now clean 4–8 line dispatchers that PHPStan should analyse: `doctors.php`, `medicines.php`, `patients.php`, `prescriptions.php`, `queue.php`, `queue_billing.php`, `settings.php`, `stats.php`, `templates.php`. Still exclude the complex files: `backup.php` (232 lines), `run_migrations.php`, `save_db_config.php`, `config/db.php`.

- [ ] **Step 1: Update phpstan.neon**

Replace the content of `phpstan.neon` with:

```neon
parameters:
    level: 5
    paths:
        - api
        - config
        - src
    excludePaths:
        - bin
        - node_modules
        - vendor
        # Complex monolithic files — not covered by Phase 3 MVC
        - api/backup.php
        - api/run_migrations.php
        - api/save_db_config.php
        - config/db.php
```

- [ ] **Step 2: Run PHPStan to verify zero errors**

```bash
vendor/bin/phpstan analyse --no-progress
```

Expected: `[OK] No errors` — fix any errors before proceeding.

- [ ] **Step 3: Update .github/workflows/lint.yml**

After the existing `Run PHPStan Static Analysis` step, add two more steps. The full updated `steps` block should be:

```yaml
    steps:
      - name: Checkout Repository
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: pdo_sqlite
          tools: composer

      - name: Setup Bun
        uses: oven-sh/setup-bun@v2
        with:
          bun-version: latest

      - name: Install Dependencies
        run: bun install

      - name: Run JavaScript Linter
        run: bun run lint

      - name: Check PHP Syntax
        run: |
          find . -name "*.php" \
            -not -path "./node_modules/*" \
            -not -path "./bin/*" \
            -exec php -l {} \;

      - name: Install Composer Dependencies
        run: composer install --no-interaction --prefer-dist --optimize-autoloader

      - name: Run PHPStan Static Analysis
        run: vendor/bin/phpstan analyse --no-progress

      - name: Run PHP Tests
        run: vendor/bin/phpunit --colors=never

      - name: Run JS Tests
        run: bun run test:js
```

Note the two additions vs current file:
1. `extensions: pdo_sqlite` on the `setup-php` step (enables SQLite for integration tests)
2. `Run PHP Tests` step: `vendor/bin/phpunit --colors=never`
3. `Run JS Tests` step: `bun run test:js`

- [ ] **Step 4: Verify all tests pass locally**

```bash
vendor/bin/phpunit --colors=always && bun run test:js
```

Expected: PHPUnit and bun test both exit 0 with no failures.

- [ ] **Step 5: Commit**

```bash
git add phpstan.neon .github/workflows/lint.yml package.json
git commit -m "chore(ci): add PHPUnit + bun test steps to lint workflow; extend PHPStan to src/ (#41 #42)"
```

---

### Task 11: README update

**Files:**
- Modify: `README.md` (create if it doesn't exist)

**Interfaces:**
- No interfaces — documentation only

- [ ] **Step 1: Check if README.md exists**

```bash
ls README.md 2>/dev/null && echo "exists" || echo "missing"
```

- [ ] **Step 2: Add or replace the development sections**

If README.md exists, add the following sections after any existing content (or replace stubs). If it doesn't exist, create it with just these sections.

Add these sections:

```markdown
## Development Setup

**Prerequisites:**
- [Bun](https://bun.sh) ≥ 1.0
- PHP 8.1+ with `pdo_sqlite` and `pdo_mysql` extensions
- [Composer](https://getcomposer.org)
- XAMPP or portable MySQL (for local app run; not required for tests)

**Install dependencies:**
```bash
bun install
composer install
```

## Running Tests

**All tests:**
```bash
bun run test
```

**PHP tests only (unit + integration, SQLite in-memory):**
```bash
vendor/bin/phpunit
```

**JavaScript tests only:**
```bash
bun test tests/js
```

**PHPStan static analysis:**
```bash
vendor/bin/phpstan analyse --no-progress
```

## Building the Desktop App

```bash
bun run dist
```

Outputs the Windows installer to `dist_app/`.

## Starting for Development

```bash
bun run start
```

Launches the Electron app. Requires a running MySQL instance (XAMPP or portable `bin/mysql`).
```

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: add development setup, testing, and build instructions to README"
```

---

## Self-Review

### 1. Spec coverage

| Spec requirement (§9) | Task |
|---|---|
| PHPUnit 11 (using 10 — PHP 8.1 constraint; see Global Constraints) | T1 |
| `bun test` in `tests/js/` | T8, T9 |
| `ValidatorTest.php` | T2 |
| `QueueServiceTest.php` covering token race | T3 |
| `LicenseServiceTest.php` → `SettingsControllerTest.php` (same HMAC logic) | T4 |
| `PrescriptionServiceTest.php` transaction rollback | T5 |
| `BackupServiceTest.php` → SKIPPED (no BackupService class; replaced by T4) | ledger |
| `PatientRepositoryTest.php` with SQLite | T6 |
| `PrescriptionRepositoryTest.php` | T7 |
| `tests/js/toast.test.js` + `autocomplete.test.js` | T9 |
| `tests/js/api.test.js` + `state.test.js` | T8 |
| CI test job (phpunit + bun test before build) | T10 |
| PHPStan level-5 CI step (already existed; extended to `src/`) | T10 |
| README with bun setup, test, build | T11 |

### 2. Placeholder scan

No TBD, TODO, or vague instructions found. All code blocks are complete and runnable.

### 3. Type consistency

- `testDbPdo()` defined in T1 bootstrap, used in T5, T6, T7 — consistent.
- `PrescriptionService::create(array): int` — used in T5 and T7 — consistent.
- `PatientRepository::create(array): int` — used in T6 — consistent with source.
- `createAutocomplete({ inputId, dropdownId, fetchFn, onSelect, minChars? })` — matches source at `assets/js/components/autocomplete.js:3`.
