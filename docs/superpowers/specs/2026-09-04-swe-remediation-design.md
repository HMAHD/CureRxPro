# CureRx Pro — SWE Remediation Design

**Date:** 2026-09-04  
**Branch:** `refactor/swe-remediation`  
**PR target:** `main`  
**Issues closed:** #26–#60 (35 total)

---

## 1. Decisions Summary

| Concern | Decision |
|---|---|
| Runtime tooling | Bun as package manager + script runner; Electron's embedded Node unchanged |
| PHP architecture | Lightweight MVC: `src/Controllers`, `src/Services`, `src/Repositories`, `src/Core` |
| Frontend | Native ES modules via `<script type="module">`; no bundler |
| Testing | PHPUnit 11 (unit + integration) + `bun test` (JS unit); no E2E in this cycle |
| Sequencing | Security triage first, then infrastructure, then architecture, then frontend, then tests |
| Commits | Small atomic commits, one logical change each; `Closes #N` in body |
| No watermarks | No AI attribution anywhere — commits, issues, PRs, code comments |

---

## 2. Phase Map

```
Phase 0 — Security Triage        ~9 commits     Closes #26 #27 #28 #29 #44 #45 #57
Phase 1 — Dev Infrastructure     ~6 commits     Closes #48 #53 + Bun setup + CI
Phase 2 — Database Foundation    ~5 commits     Closes #33 #39 #56 #58 (schema)
Phase 3 — PHP Lightweight MVC   ~40 commits     Closes #32 #34 #35 #36 #37 #38 #40
                                                 #43 #46 #47 #50 #51 #59 #60
Phase 4 — Frontend ES Modules   ~14 commits     Closes #30 #31 #52 #54 #55
Phase 5 — Testing + CI           ~12 commits    Closes #41 #42
```

Total: ~86 small commits, one PR, review, merge.

---

## 3. Branch Setup

```bash
git checkout -b refactor/swe-remediation
```

All work happens on this branch. No force-pushes. Rebase not required — merge commit is fine for a remediation PR of this scope.

---

## 4. Phase 0 — Security Triage

### 4.1 Commit sequence

| # | Commit message | Issue |
|---|---|---|
| 1 | `chore(gitignore): exclude api_secret.key, db_config.json, and schema flag` | #29 |
| 2 | `fix(security): remove simulate_subscription.php debug endpoint` | #26 |
| 3 | `fix(security): remove clear_database.php admin utility from web root` | #26 |
| 4 | `fix(security): remove admin_license_generator.php from web root` | #26 |
| 5 | `fix(security): lock auto-update downloads to github.com releases domain` | #27 |
| 6 | `fix(security): replace CRX string-match license check with HMAC-SHA256` | #28 |
| 7 | `fix(security): apply escapeHtml consistently across all app.js template literals` | #44 |
| 8 | `fix(security): remove API token query-string fallback from auth_check.php` | #45 |
| 9 | `fix(security): encrypt database backup export with AES-256-CBC` | #57 |

### 4.2 HMAC license key design (commit 6)

Keys are signed server-side: `HMAC-SHA256(plan|expiry_unix, CURERX_SECRET)`, hex-encoded, prefixed `CRX-`. Validation recomputes the HMAC and compares with `hash_equals()`. The secret lives in an env var `CURERX_LICENSE_SECRET` (never in source). Old keys in the database are migrated at first settings-save.

### 4.3 Auto-update domain lock (commit 5)

`main.js` hardcodes the allowed host: `github.com`. Any URL from the renderer that does not match `https://github.com/HMAHD/CureRxPro/releases/download/` is rejected before download begins. The download URL is no longer accepted from the renderer at all — `main.js` constructs it from the version tag received.

### 4.4 Backup encryption (commit 9)

Export: PHP generates a random 16-byte IV, encrypts with `openssl_encrypt(json, 'AES-256-CBC', key, 0, iv)`, prepends IV, base64-encodes. File extension changes to `.crxbak`. Import: reverses. The encryption key is derived from the clinic's `api_secret.key` using `hash('sha256', $secret)`. No additional password needed for v1 — the backup is already tied to the installation's secret.

---

## 5. Phase 1 — Dev Infrastructure

### 5.1 Commit sequence

| # | Commit message | Issue |
|---|---|---|
| 1 | `chore(bun): replace npm with bun as package manager` | — |
| 2 | `chore(deps): pin all dependency versions to exact semver` | #53 |
| 3 | `chore(gitignore): add bun.lockb, remove package-lock.json` | — |
| 4 | `chore(ci): switch GitHub Actions workflows to bun install and bun run` | — |
| 5 | `chore(ci): add PHPStan level-5 static analysis step` | #42 |
| 6 | `fix(electron): add OS platform detection for MySQL and PHP binary paths` | #48 |

### 5.2 Bun setup

- Add `bunfig.toml` at project root (minimal: `[install] exact = true`)
- Replace `npm install` → `bun install` everywhere (README, CI, scripts)
- `package.json` scripts: `"start": "bun run electron ."`, `"test:js": "bun test"`
- `package-lock.json` deleted; `bun.lockb` committed
- `.gitignore`: add `bun.lockb` is tracked (committed), exclude `node_modules/` (already present)

### 5.3 Platform detection (commit 6)

```js
// main.js
const isWin = process.platform === 'win32';
const EXE = isWin ? '.exe' : '';
const mysqlBin = path.join(__dirname, 'bin', 'mysql', 'bin', `mysqld${EXE}`);
const localPhp  = path.join(__dirname, 'bin', 'php', `php${EXE}`);
```

macOS fallback: check `/usr/local/bin/php`, `/opt/homebrew/bin/php` in order. Show a clear dialog if nothing found rather than silently failing.

---

## 6. Phase 2 — Database Foundation

### 6.1 Commit sequence

| # | Commit message | Issue |
|---|---|---|
| 1 | `feat(db): add schema_migrations table and Migrations runner` | #33 |
| 2 | `refactor(db): remove ALTER TABLE try/catch blocks from init_tables` | #33 |
| 3 | `feat(migration): 005_performance_indexes for FK and date columns` | #56 |
| 4 | `feat(migration): 006_soft_deletes on patients and prescriptions` | #39 |
| 5 | `feat(migration): 007_audit_log table` | #58 |

### 6.2 Migration runner design

`src/Core/Migrations.php` — runs on every app boot, idempotent:

```php
class Migrations {
    public function run(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            filename VARCHAR(255) PRIMARY KEY,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $applied = $pdo->query("SELECT filename FROM schema_migrations")
                       ->fetchAll(PDO::FETCH_COLUMN);
        foreach (glob(__DIR__ . '/../../scripts/migrations/*.sql') as $file) {
            $name = basename($file);
            if (!in_array($name, $applied, true)) {
                $pdo->exec(file_get_contents($file));
                $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?)")
                    ->execute([$name]);
            }
        }
    }
}
```

`init_tables()` in `db.php` retains only `CREATE TABLE IF NOT EXISTS` base DDL (no ALTER TABLE). All column additions move into numbered migration files.

### 6.3 Soft delete migration (006)

Adds `deleted_at DATETIME NULL DEFAULT NULL` to `patients` and `prescriptions`. All SELECT queries in repositories add `WHERE deleted_at IS NULL`. DELETE endpoints become `UPDATE ... SET deleted_at = NOW()`. A `GET /api/patients?include_deleted=true` (admin only) exposes them for recovery.

### 6.4 Audit log migration (007)

```sql
CREATE TABLE audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    action ENUM('create','update','delete') NOT NULL,
    table_name VARCHAR(64) NOT NULL,
    record_id INT NOT NULL,
    old_values JSON,
    new_values JSON,
    workstation VARCHAR(100),
    performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_table_record (table_name, record_id),
    INDEX idx_audit_performed_at (performed_at)
) ENGINE=InnoDB;
```

---

## 7. Phase 3 — PHP Lightweight MVC

### 7.1 Directory structure

```
src/
  Core/
    Database.php         PDO singleton with setConnection() for tests
    Response.php         json_response(), error_response() — sets HTTP status
    Validator.php        Chainable: ->required()->integer()->decimal()->date()->enum()
    Request.php          Clean wrapper: method(), get(), post(), header()
    Migrations.php       Auto-runs pending migration files
    ErrorHandler.php     Global set_exception_handler() + structured file log
    AuditLogger.php      Writes rows to audit_log table
  Controllers/
    StatsController.php
    PatientController.php
    PrescriptionController.php
    MedicineController.php
    QueueController.php
    BillingController.php
    DoctorController.php
    TemplateController.php
    SettingsController.php
    BackupController.php
  Services/
    QueueService.php        Token generation with SELECT FOR UPDATE
    PrescriptionService.php Full transaction: prescription + items + queue update
    LicenseService.php      HMAC-SHA256 key generation and validation
    BackupService.php       Chunked export, encrypted import, per-table reporting
  Repositories/
    PatientRepository.php
    PrescriptionRepository.php
    MedicineRepository.php
    QueueRepository.php
    BillingRepository.php
    DoctorRepository.php
    TemplateRepository.php
    SettingsRepository.php
  bootstrap.php            Wires autoloader, Database, ErrorHandler, Migrations
```

PSR-4 autoloading via a minimal `spl_autoload_register` in `bootstrap.php` — no Composer required for the app runtime (Composer used for dev/test only via `require-dev`).

### 7.2 api/*.php become dispatchers

```php
<?php
// api/patients.php — 4 lines
require_once __DIR__ . '/../src/bootstrap.php';
$ctrl = new \CureRx\Controllers\PatientController(
    new \CureRx\Repositories\PatientRepository(\CureRx\Core\Database::get())
);
$ctrl->handle(\CureRx\Core\Request::fromGlobals());
```

No business logic, no SQL, no try/catch in endpoint files.

### 7.3 Response helper

```php
// Response::json(['status'=>'success','data'=>$data], 200)
// Response::error('Validation failed', 422, $errors)
class Response {
    public static function json(array $data, int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    public static function error(string $message, int $status = 400, array $errors = []): never {
        self::json(['status' => 'error', 'message' => $message, 'errors' => $errors], $status);
    }
}
```

### 7.4 Validator

```php
$v = (new Validator($_POST))
    ->required('name')
    ->integer('age', min: 0, max: 150)
    ->decimal('consultation_fee', min: 0)
    ->date('follow_up_date')
    ->enum('gender', ['Male','Female','Other']);

if ($v->fails()) {
    Response::error('Validation failed', 422, $v->errors());
}
```

### 7.5 Per-endpoint commit sequence (example: patients)

```
refactor(patients): add PatientRepository with parameterized find/search/create/update/softDelete
refactor(patients): add PatientController dispatching to repository
fix(patients): validate name, age, gender, phone before INSERT         Closes #50 (partial)
fix(patients): return 201 on create, 404 on not-found, 422 on invalid  Closes #38 (partial)
test(patients): PHPUnit tests for PatientRepository and PatientController
```

Repeat this 5-commit pattern for each of the 10 controllers. Higher-complexity services (Queue, Prescription, Backup) get additional commits for the service class and its specific bug fix.

### 7.6 Queue token fix (QueueService)

```php
public function assignToken(int $doctorId): int {
    $this->db->beginTransaction();
    $stmt = $this->db->prepare(
        "SELECT COALESCE(MAX(token_number), 0) + 1
         FROM waiting_queue
         WHERE doctor_id = ? AND DATE(created_at) = CURDATE()
         FOR UPDATE"
    );
    $stmt->execute([$doctorId]);
    $token = (int)$stmt->fetchColumn();
    // INSERT uses $token
    $this->db->commit();
    return $token;
}
```
Closes #36.

### 7.7 N+1 query fix (PrescriptionRepository)

Replace per-row item queries with a single JOIN, group in PHP:

```php
public function findAllForPatient(int $patientId): array {
    $stmt = $this->db->prepare("
        SELECT p.*, pi.id as item_id, pi.medicine_name, pi.strength,
               pi.dosage, pi.instruction, pi.duration, pi.quantity,
               pi.unit_price, pi.item_total
        FROM prescriptions p
        LEFT JOIN prescription_items pi ON pi.prescription_id = p.id
        WHERE p.patient_id = ? AND p.deleted_at IS NULL
        ORDER BY p.id DESC
    ");
    $stmt->execute([$patientId]);
    return $this->groupItemsByPrescription($stmt->fetchAll());
}
```
Closes #34.

### 7.8 MySQL startup health check (main.js)

Replace fixed `setTimeout(resolve, 2000)` with TCP port poll:

```js
function waitForPort(port, host = '127.0.0.1', maxMs = 20000) {
    return new Promise((resolve) => {
        const net = require('net');
        const start = Date.now();
        const attempt = () => {
            const sock = net.createConnection({ port, host });
            sock.on('connect', () => { sock.destroy(); resolve(true); });
            sock.on('error', () => {
                if (Date.now() - start > maxMs) resolve(false);
                else setTimeout(attempt, 300);
            });
        };
        attempt();
    });
}
```
Closes #40.

### 7.9 Seed data separation (commit for #43)

Move all hardcoded defaults out of `db.php` into `seeds/`:

```
seeds/
  default_clinic.json       doctor_name, clinic_name, address, phone, slmc_reg
  default_medicines.json    array of 15 medicine objects
  default_templates.json    fever+cold, gastritis templates
```

`init_tables()` reads from these files. Clinics can ship their own seed files without touching PHP source.

### 7.10 Backup service (BackupService)

Export: streams table rows in 500-row pages, writes to a temp file, encrypts, serves. No full table-in-memory load.  
Import: validates ALL expected tables present before truncating anything. Reports per-table success/failure. Ensures `FOREIGN_KEY_CHECKS = 1` in a `finally` block.  
Closes #35, #47.

---

## 8. Phase 4 — Frontend ES Modules

### 8.1 Directory structure

```
assets/js/
  api.js                  fetch() wrapper; injects X-CureRx-Token header automatically
  state.js                Single state object; typed getters/setters; no direct mutation
  main.js                 Entry point: imports modules, wires event delegation
  components/
    toast.js              showToast(message, type='info', duration=3000)
    autocomplete.js       createAutocomplete({inputId, dropdownId, fetchFn, onSelect})
    modal.js              openModal(id), closeModal(id), closeAllModals()
    print.js              triggerPrint(html, paperSize)
  modules/
    patients.js
    prescriptions.js      includes localStorage draft auto-save
    queue.js
    billing.js
    medicines.js          includes pagination controls
    history.js
    templates.js
    settings.js
    stats.js

views/
  layout/
    head.php              <head> tag, CSS links, CSP meta
    sidebar.php           <aside> nav
    header.php            top header bar + doctor select + language select
  tabs/
    tab-builder.php
    tab-patients.php
    tab-queue.php
    tab-billing.php
    tab-medicines.php
    tab-history.php
    tab-templates.php
    tab-settings.php
  modals/
    modal-print.php
    modal-patient.php
    modal-medicine.php
```

`index.php` becomes ~25 lines: require layout partials, loop tab partials, close body.

### 8.2 api.js design

```js
// assets/js/api.js
const BASE = 'http://127.0.0.1:8989/api';

async function request(endpoint, options = {}) {
    const res = await fetch(`${BASE}${endpoint}`, {
        headers: {
            'Content-Type': 'application/json',
            'X-CureRx-Token': window.__crxToken ?? ''
        },
        ...options
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({ message: res.statusText }));
        throw new Error(err.message ?? 'Request failed');
    }
    return res.json();
}

export const get  = (url, params) => request(url + (params ? '?' + new URLSearchParams(params) : ''));
export const post = (url, body)   => request(url, { method: 'POST',   body: JSON.stringify(body) });
export const put  = (url, body)   => request(url, { method: 'PUT',    body: JSON.stringify(body) });
export const del  = (url)         => request(url, { method: 'DELETE' });
```

The token (`window.__crxToken`) is injected by `index.php` via a PHP-generated `<script>` tag — never hardcoded in JS source.

### 8.3 Autocomplete factory (closes #54)

```js
// assets/js/components/autocomplete.js
export function createAutocomplete({ inputId, dropdownId, fetchFn, onSelect, minChars = 2 }) {
    const input    = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    let timer;

    input.addEventListener('input', () => {
        clearTimeout(timer);
        if (input.value.length < minChars) { dropdown.hidden = true; return; }
        timer = setTimeout(async () => {
            const results = await fetchFn(input.value);
            renderDropdown(dropdown, results, onSelect, input);
        }, 250);
    });
}
```

Used twice with different config — no duplication.

### 8.4 Draft auto-save (closes #52)

In `modules/prescriptions.js`, debounced save to `localStorage` on any field change:

```js
function saveDraft() {
    localStorage.setItem('rx_draft', JSON.stringify({
        patientId: state.get('selectedPatient')?.id,
        medicines: state.get('rxMedicines'),
        fields: collectFormFields()
    }));
}

function restoreDraft() {
    const raw = localStorage.getItem('rx_draft');
    if (!raw) return;
    const draft = JSON.parse(raw);
    showToast('Unsaved prescription found. Restored.', 'info');
    applyDraft(draft);
}
```

Draft is cleared on successful save.

### 8.5 Commit sequence

| # | Commit message | Issue |
|---|---|---|
| 1 | `refactor(views): split index.php into layout and tab partials` | #30 |
| 2 | `feat(js): add api.js fetch wrapper with token header injection` | — |
| 3 | `feat(js): add state.js with typed getters and setters` | — |
| 4 | `feat(js): add toast component; replace all alert() calls` | #55 |
| 5 | `feat(js): add autocomplete factory component` | #54 |
| 6 | `feat(js): add modal utility component` | — |
| 7 | `refactor(js): extract patients module from app.js` | #31 (partial) |
| 8 | `refactor(js): extract queue module from app.js` | #31 (partial) |
| 9 | `refactor(js): extract billing module from app.js` | #31 (partial) |
| 10 | `refactor(js): extract medicines module with pagination` | #31 #59 |
| 11 | `refactor(js): extract prescriptions module from app.js` | #31 (partial) |
| 12 | `feat(prescriptions): add localStorage draft auto-save on field change` | #52 |
| 13 | `refactor(js): extract history, templates, settings, stats modules` | #31 |
| 14 | `refactor(js): wire all modules in main.js; delete legacy app.js` | #31 |

---

## 9. Phase 5 — Testing + CI

### 9.1 PHP testing setup

```
composer.json (require-dev):
  phpunit/phpunit: ^11
  
tests/
  Unit/
    Core/
      ValidatorTest.php
      DatabaseTest.php
    Services/
      QueueServiceTest.php          covers token race condition logic
      LicenseServiceTest.php        covers HMAC generate + validate
      PrescriptionServiceTest.php   covers transaction rollback on item failure
      BackupServiceTest.php         covers per-table validation
  Integration/
    Repositories/
      PatientRepositoryTest.php     requires test DB (SQLite in-memory)
      PrescriptionRepositoryTest.php
      QueueRepositoryTest.php
  bootstrap.php                     sets up in-memory SQLite for integration tests
```

### 9.2 JS testing setup

```
tests/js/
  components/
    toast.test.js
    autocomplete.test.js
  api.test.js          mocks fetch; tests error handling, token injection
  state.test.js        tests getters, setters, isolation
```

`package.json`:
```json
"scripts": {
  "test:js":  "bun test tests/js",
  "test:php": "vendor/bin/phpunit",
  "test":     "bun run test:php && bun run test:js"
}
```

### 9.3 CI commits

| # | Commit message | Issue |
|---|---|---|
| 1 | `chore(test): add composer.json with PHPUnit 11 dev dependency` | #41 |
| 2 | `test(core): add ValidatorTest covering all rule types` | — |
| 3 | `test(services): add QueueServiceTest covering token uniqueness` | — |
| 4 | `test(services): add LicenseServiceTest for HMAC validation` | — |
| 5 | `test(services): add PrescriptionServiceTest for transaction rollback` | — |
| 6 | `test(integration): add PatientRepositoryTest with SQLite fixture` | — |
| 7 | `test(integration): add PrescriptionRepositoryTest` | — |
| 8 | `test(js): add toast and autocomplete component tests` | — |
| 9 | `test(js): add api.js and state.js unit tests` | — |
| 10 | `chore(ci): add test job running phpunit and bun test before build` | #41 |
| 11 | `chore(ci): add PHPStan level-5 to CI test job` | #42 |
| 12 | `docs: update README with bun setup, test, and build instructions` | — |

---

## 10. Issue-to-Phase Mapping (complete)

| Issue | Title (short) | Phase | Commit type |
|---|---|---|---|
| #26 | Debug endpoints in web root | 0 | fix ×3 |
| #27 | RCE via auto-update | 0 | fix |
| #28 | License bypass (HMAC replace) | 0 + 3 | fix + feat |
| #29 | Secrets not in .gitignore | 0 | chore |
| #30 | Monolithic index.php | 4 | refactor |
| #31 | Monolithic app.js | 4 | refactor ×8 |
| #32 | No MVC layers | 3 | refactor |
| #33 | Dual migration systems | 2 | feat + refactor |
| #34 | N+1 queries | 3 | fix |
| #35 | Backup OOM | 3 | fix |
| #36 | Token race condition | 3 | fix |
| #37 | Missing transactions | 3 | fix |
| #38 | HTTP 200 for all errors | 3 | fix |
| #39 | No soft deletes | 2 + 3 | feat |
| #40 | MySQL startup no health check | 3 | fix |
| #41 | No tests | 5 | test + chore |
| #42 | No PHP static analysis in CI | 1 + 5 | chore |
| #43 | Hardcoded seed data | 3 | refactor |
| #44 | XSS in app.js | 0 | fix |
| #45 | Token in query string | 0 | fix |
| #46 | No error handling/logging | 3 | feat |
| #47 | Backup restore no safety | 3 | fix |
| #48 | Windows-only binary paths | 1 | fix |
| #49 | stats.php full table scans | 3 | fix |
| #50 | No input validation layer | 3 | feat |
| #51 | Boilerplate duplication | 3 | refactor |
| #52 | No draft auto-save | 4 | feat |
| #53 | Unpinned npm deps | 1 | chore |
| #54 | Duplicate autocomplete | 4 | refactor |
| #55 | alert() everywhere | 4 | feat |
| #56 | Missing DB indexes | 2 | feat |
| #57 | Unencrypted backup | 0 | fix |
| #58 | No audit trail | 2 + 3 | feat |
| #59 | Medicines LIMIT 100 | 4 | fix |
| #60 | Global $pdo | 3 | refactor |

---

## 11. What Does Not Change

- Electron window configuration, menu, and icon
- PHP built-in server invocation pattern
- MySQL portable server setup (only startup health check improved)
- `assets/css/style.css` and `assets/css/print.css` — untouched
- `db_setup.php` — out of scope for this cycle
- `tools/data-pipeline/` Python scripts — out of scope

---

## 12. Definition of Done

- All 35 issues closed via commit `Closes #N` references
- `bun test` and `vendor/bin/phpunit` both pass with zero failures
- PHPStan level 5 passes with zero errors
- ESLint passes with zero warnings
- PR opened against `main` with full issue checklist
- No hardcoded credentials, debug endpoints, or watermarks remain
