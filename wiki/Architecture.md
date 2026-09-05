# Architecture

## Layer Overview

```
Electron Shell (main.js / preload.js)
    |
    |  spawns
    v
PHP Built-in Server :8989  <--  HTTP/JSON  --  Renderer (BrowserWindow)
    |
    |  PDO
    v
MySQL :3307
```

The frontend communicates with the backend exclusively over HTTP. There is no direct IPC between the renderer and the PHP process — all data flows through the API.

---

## Frontend

**Technology:** Plain HTML, CSS, native ES modules (`<script type="module">`). No bundler, no transpiler.

**Entry point:** `index.php` renders the shell layout and injects:
- `window.__crxToken` — HMAC-signed session token generated server-side
- `assets/js/main.js` (module) — imports and initialises all feature modules

**Module map:**

```
assets/js/
├── main.js                  # navigation wiring, imports all modules
├── components/
│   ├── autocomplete.js      # patient/medicine search dropdown
│   ├── modal.js             # openModal / closeModal (classList.active mechanism)
│   └── toast.js             # showToast notification
├── modules/
│   ├── api.js               # fetch wrapper; injects X-CureRx-Token header
│   ├── state.js             # lightweight key-value store (no reactivity)
│   ├── queue.js             # queue monitor, polling lifecycle
│   ├── medicines.js         # medicine CRUD
│   ├── prescriptions.js     # prescription builder
│   ├── history.js           # visit history
│   ├── billing.js           # channeling billing
│   ├── templates.js         # clinical templates
│   ├── settings.js          # clinic settings, lang switcher
│   └── stats.js             # dashboard statistics
```

**Cross-module communication:** Custom DOM events with the `crx:` prefix (e.g. `crx:reloadQueue`, `crx:patientSelected`). No shared mutable state between modules except via `state.js`.

**Modal mechanism:** `.modal-overlay { display: none }` + `.modal-overlay.active { display: flex }` — always use `classList.add/remove('active')`, never `style.display`.

---

## PHP Backend

**PSR-4 autoloaded source tree:**

```
src/
├── Controllers/
│   └── SettingsController.php   # settings API, license validation
├── Core/
│   ├── AuditLogger.php          # writes to audit_log table
│   └── Validator.php            # chainable field validation rules
├── Repositories/
│   ├── PatientRepository.php    # patient CRUD + search
│   └── PrescriptionRepository.php
└── Services/
    ├── PrescriptionService.php  # transactional prescription creation
    └── QueueService.php         # queue token assignment (uses SELECT FOR UPDATE)
```

**Dispatcher pattern:** Each file in `api/` is 5–10 lines: require the autoloader, instantiate the relevant Controller/Service with a PDO connection, call `handle()`, and `echo json_encode(...)`.

**Database access:** All queries use PDO prepared statements. The PDO instance is created in `config/db.php` and injected by the dispatcher.

**Authentication:** Every API request must carry an `X-CureRx-Token` header. `config/db.php` verifies the HMAC-SHA256 signature before returning the PDO instance. Unauthenticated requests receive `401`.

---

## Electron Shell

`main.js` (Node.js / Electron main process):
- Spawns and monitors the PHP and MySQL child processes
- Creates the `BrowserWindow` pointing at `http://localhost:8989`
- Manages the hidden silent-print worker window via IPC
- Handles auto-update via `electron-updater`

`preload.js` exposes a minimal context bridge to the renderer (file I/O for print receipts; no raw Node.js access exposed to renderer code).

---

## Database Schema

Key tables (MySQL):

| Table | Purpose |
| :--- | :--- |
| `patients` | Patient demographics, soft-delete flag |
| `doctors` | Doctor profiles |
| `prescriptions` | Prescription header (patient, doctor, diagnosis, date) |
| `prescription_items` | Drug lines per prescription |
| `waiting_queue` | Token queue entries with status |
| `medicines` | Drug master catalog |
| `audit_log` | Append-only log of data mutations |
| `settings` | Key-value clinic configuration store |

Migrations live in `scripts/migrations/` as numbered SQL files and are applied by `api/run_migrations.php`.

---

## Security Model

1. **SQL injection:** All queries use `PDO::prepare()` + bound parameters. No string interpolation in SQL.
2. **API authentication:** `window.__crxToken` is an HMAC-SHA256 token signed with a per-installation secret stored in `config/db_config.json`. The PHP layer verifies it on every request.
3. **License validation:** `SettingsController::validateLicenseKey()` verifies a `CRX-{base64url_payload}-{hmac}` key format using HMAC-SHA256.
4. **Audit log:** Every mutation goes through `AuditLogger::log()` which records the table, action, actor, and timestamp.
