# Testing Guide

## Overview

| Suite | Tool | Location | Count |
| :--- | :--- | :--- | :---: |
| PHP unit | PHPUnit 10 | `tests/Unit/` | 31 |
| PHP integration | PHPUnit 10 + SQLite | `tests/Integration/` | 19 |
| JavaScript | bun + happy-dom | `tests/js/` | 30 |

Integration tests use an **in-memory SQLite database**. No MySQL instance is required to run the test suite.

---

## Running Tests

### All tests at once

```bash
bun run test
```

This runs `vendor/bin/phpunit` then `bun test tests/js/` sequentially via the `test` script in `package.json`.

### PHP tests only

```bash
vendor/bin/phpunit --testdox
```

By suite:

```bash
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration
```

By file:

```bash
vendor/bin/phpunit tests/Unit/Core/ValidatorTest.php --testdox
```

### JavaScript tests only

```bash
bun test tests/js/
```

By file:

```bash
bun test tests/js/components/toast.test.js
```

### Static analysis

```bash
vendor/bin/phpstan analyse --no-progress
```

Level 5. Config in `phpstan.neon`.

---

## PHP Test Architecture

### Bootstrap

`tests/bootstrap.php` provides:
- `require_once __DIR__ . '/../vendor/autoload.php'` — loads the autoloader
- `testDbPdo(): PDO` — returns a fresh SQLite in-memory PDO with the full application schema (patients, doctors, prescriptions, prescription_items, waiting_queue, audit_log tables)

### SQLite compatibility rules

MySQL-specific constructs not supported by SQLite are handled as follows:

| Construct | Strategy |
| :--- | :--- |
| `SELECT ... FOR UPDATE` | Mock PDO (no real DB) |
| `CURDATE()`, `NOW()` | Skip the method or mock PDO |
| `AUTO_INCREMENT` | Changed to `AUTOINCREMENT` in test DDL |
| `ENUM(...)` | Changed to `TEXT` in test DDL |

### Unit tests (`tests/Unit/`)

Use mock PDO objects (`$this->createMock(\PDO::class)`) for anything requiring MySQL-specific behaviour. Use `ReflectionMethod::setAccessible(true)` to test private methods.

Files:

| File | Class under test | Approach |
| :--- | :--- | :--- |
| `Core/ValidatorTest.php` | `Validator` | Pure PHP, no DB |
| `Services/QueueServiceTest.php` | `QueueService` | Mock PDO (uses `FOR UPDATE`) |
| `Controllers/SettingsControllerTest.php` | `SettingsController::validateLicenseKey` | Reflection + no DB |

### Integration tests (`tests/Integration/`)

Use `testDbPdo()` in `setUpBeforeClass()`. Reset table state in `setUp()` with `DELETE FROM` in FK-safe order.

Files:

| File | Class under test | Approach |
| :--- | :--- | :--- |
| `Services/PrescriptionServiceTest.php` | `PrescriptionService` | SQLite happy path + mock rollback |
| `Repositories/PatientRepositoryTest.php` | `PatientRepository` | SQLite |
| `Repositories/PrescriptionRepositoryTest.php` | `PrescriptionRepository` | SQLite |

---

## JavaScript Test Architecture

### Setup

`bunfig.toml` preloads `tests/js/setup.js` before every test file. `setup.js` registers `happy-dom` globals (window, document) and sets `window.__crxToken` to a test value.

### Writing a new JS test

```js
import { describe, test, expect, beforeEach } from 'bun:test';
import { myFunction } from '../../assets/js/modules/my-module.js';

describe('myFunction', () => {
    test('does the thing', () => {
        expect(myFunction('input')).toBe('expected output');
    });
});
```

Put the file in `tests/js/` (or `tests/js/components/` for component tests). `bun test tests/js/` discovers it automatically.

### DOM tests

`happy-dom` provides `document`, `window`, and `Event`. Set up DOM nodes in `beforeEach`, clean up in `afterEach`:

```js
beforeEach(() => {
    document.body.innerHTML = '<div id="my-el"></div>';
});
afterEach(() => {
    document.body.innerHTML = '';
});
```

---

## Writing New PHP Tests

### Unit test skeleton

```php
<?php

namespace CureRx\Tests\Unit\Services;

use CureRx\Services\MyService;
use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase
{
    public function testDoesTheThing(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockPdo  = $this->createMock(\PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStmt);
        $mockStmt->method('execute')->willReturn(true);

        $result = (new MyService($mockPdo))->doTheThing('arg');

        $this->assertSame('expected', $result);
    }
}
```

### Integration test skeleton

```php
<?php

namespace CureRx\Tests\Integration\Repositories;

use CureRx\Repositories\MyRepository;
use PHPUnit\Framework\TestCase;

class MyRepositoryTest extends TestCase
{
    private static \PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = testDbPdo();
    }

    protected function setUp(): void
    {
        self::$pdo->exec('DELETE FROM my_table');
    }

    public function testCreateReturnsId(): void
    {
        $repo = new MyRepository(self::$pdo);
        $id   = $repo->create(['name' => 'Test']);
        $this->assertGreaterThan(0, $id);
    }
}
```

---

## CI

`.github/workflows/lint.yml` runs on every push:

1. Setup PHP 8.1 with `pdo_sqlite` extension
2. `composer install`
3. `vendor/bin/phpstan analyse`
4. `vendor/bin/phpunit --colors=never`
5. Setup Bun
6. `bun install`
7. `bun run test:js`

All four steps must be green for a PR to be mergeable.
