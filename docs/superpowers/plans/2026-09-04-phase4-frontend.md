# Phase 4 — Frontend ES Modules Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Break the 2868-line monolithic `assets/js/app.js` and 1072-line `index.php` into focused ES modules and view partials, replace all `alert()`/`confirm()` with a toast component, and extract a reusable autocomplete factory.

**Architecture:** Native `<script type="module">` — no bundler. `assets/js/main.js` is the entry point importing all modules. View HTML lives in `views/layout/`, `views/tabs/`, `views/modals/`. Each JS module exports an `init()` function. Shared state flows through `state.js`; all fetch calls go through `api.js`.

**Tech Stack:** Vanilla JS ES2022 (top-level module imports), PHP 8, no new npm packages.

**Spec:** `docs/superpowers/specs/2026-09-04-swe-remediation-design.md` §8

## Global Constraints

- Branch: `refactor/swe-remediation`
- No AI attribution or watermarks in commits
- No bundler, no new npm/Bun packages — native ES modules only
- `<script type="module">` in index.php; no `defer` needed (modules are deferred by default)
- `window.__crxToken` injected by index.php before the module script tag
- All `alert()` calls replaced with `showToast()`; all `confirm()` calls replaced with `window.confirm()` wrapped in a check (keep confirm() — toast can't block)
- Do NOT add `type="module"` to the old `<script src="app.js">` tag — remove it entirely in Task 10
- `escapeHtml()` and `debounce()` are utility functions; copy them into `api.js` as named exports
- CLAUDE.md not touched

---

### Task 1: Split index.php into view partials (#30)

**Files:**
- Create directory: `views/layout/`, `views/tabs/`, `views/modals/`
- Create: `views/layout/head.php`
- Create: `views/layout/sidebar.php`
- Create: `views/layout/header.php`
- Create: `views/tabs/tab-builder.php` (lines 160–363 of current index.php)
- Create: `views/tabs/tab-patients.php` (lines 364–390)
- Create: `views/tabs/tab-queue.php` (lines 391–402 + queue monitor section)
- Create: `views/tabs/tab-billing.php` (lines 403–495)
- Create: `views/tabs/tab-medicines.php` (lines 496–537)
- Create: `views/tabs/tab-history.php` (lines 538–579)
- Create: `views/tabs/tab-templates.php` (lines 580–594)
- Create: `views/tabs/tab-settings.php` (lines 595–771)
- Create: `views/modals/modal-print.php` (lines 772–809)
- Create: `views/modals/modal-patient.php` (lines 810–877)
- Create: `views/modals/modal-medicine.php` (lines 878–929)
- Create: `views/modals/modal-patient-history.php` (lines 930–966)
- Create: `views/modals/modal-pediatric-calc.php` (lines 967–1007)
- Create: `views/modals/modal-auto-update.php` (lines 1008+)
- Modify: `index.php` (reduce to ~50 lines)

**Interfaces:**
- Produces: all view partials reachable via `require_once`
- Produces: `index.php` orchestrator that requires layout + loops tabs + includes modals

- [ ] **Step 1: Read index.php in full**

Read the entire `/Users/akash/Downloads/cureRXPro/index.php` to understand exactly what HTML lives in each section.

- [ ] **Step 2: Create views/ directory structure**

```bash
mkdir -p views/layout views/tabs views/modals
```

- [ ] **Step 3: Create views/layout/head.php**

Extract the entire `<head>` block from index.php (from `<!DOCTYPE html>` through `</head>`). Keep the inline `<style>` for role-based tab visibility. Replace the hardcoded `<script src="assets/js/app.js">` at the bottom with nothing (that will move to the new orchestrator index.php). The `$app_role` PHP variable is used in the inline style — ensure it's available (it's set in index.php before the require).

- [ ] **Step 4: Create views/layout/sidebar.php**

Extract the `<aside class="app-sidebar">` block.

- [ ] **Step 5: Create views/layout/header.php**

Extract the top header bar (doctor select dropdown, language select, any top controls).

- [ ] **Step 6: Create each tab partial**

For each tab section (`<section class="tab-content" id="tab-*">`), extract it to `views/tabs/tab-{name}.php`. Each file is just the `<section>` element — no PHP tags needed unless the section uses PHP variables.

Tab names and their section IDs:
- `tab-builder.php` → `id="tab-builder"`
- `tab-patients.php` → `id="tab-patients"`
- `tab-queue.php` → `id="tab-queue-monitor"` (the queue monitor section)
- `tab-billing.php` → `id="tab-channeling-billing"`
- `tab-medicines.php` → `id="tab-medicines"`
- `tab-history.php` → `id="tab-history"`
- `tab-templates.php` → `id="tab-templates"`
- `tab-settings.php` → `id="tab-settings"`

- [ ] **Step 7: Create each modal partial**

For each `<div class="modal-overlay" id="modal-*">`, extract to `views/modals/modal-{name}.php`:
- `modal-print.php` → `id="modal-print-preview"`
- `modal-patient.php` → `id="modal-patient"`
- `modal-medicine.php` → `id="modal-medicine"`
- `modal-patient-history.php` → `id="modal-patient-history"`
- `modal-pediatric-calc.php` → `id="modal-pediatric-calc"`
- `modal-auto-update.php` → `id="modal-auto-update"`

- [ ] **Step 8: Rewrite index.php to ~50 lines**

```php
<?php
require_once __DIR__ . '/config/db.php';
if (!$db_connected) {
    include __DIR__ . '/db_setup.php';
    exit;
}

$app_role = 'both';
$configPath = __DIR__ . '/config/db_config.json';
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
    $app_role = $config['app_role'] ?? 'both';
}

require_once __DIR__ . '/config/auth_check.php';
$crx_token = get_api_secret();

require_once __DIR__ . '/views/layout/head.php';
?>
<body class="role-<?php echo htmlspecialchars($app_role); ?>">

<?php require_once __DIR__ . '/views/layout/sidebar.php'; ?>
<?php require_once __DIR__ . '/views/layout/header.php'; ?>

<main class="app-main">
<?php
$tabs = ['tab-builder','tab-patients','tab-queue','tab-billing',
         'tab-medicines','tab-history','tab-templates','tab-settings'];
foreach ($tabs as $tab) {
    require_once __DIR__ . "/views/tabs/{$tab}.php";
}
?>
</main>

<?php
$modals = ['modal-print','modal-patient','modal-medicine',
           'modal-patient-history','modal-pediatric-calc','modal-auto-update'];
foreach ($modals as $modal) {
    require_once __DIR__ . "/views/modals/{$modal}.php";
}
?>

<script>window.__crxToken = '<?php echo htmlspecialchars($crx_token, ENT_QUOTES); ?>';</script>
<script src="assets/js/app.js"></script>
</body>
</html>
```

Note: `app.js` stays for now — Task 10 removes it and replaces with `<script type="module" src="assets/js/main.js">`.

- [ ] **Step 9: Verify the page still loads**

Open a browser (or check that index.php has no PHP syntax errors):
```bash
php -l index.php
for f in views/layout/*.php views/tabs/*.php views/modals/*.php; do php -l "$f"; done
```

- [ ] **Step 10: Commit**

```bash
git add views/ index.php
git commit -m "refactor(views): split index.php into layout and tab partials

Extracts 1000+ lines of mixed HTML/PHP into focused partials.
index.php is now ~50 lines. app.js wiring unchanged.

Closes #30"
```

---

### Task 2: Create api.js and state.js

**Files:**
- Create: `assets/js/api.js`
- Create: `assets/js/state.js`

**Interfaces:**
- `api.js` exports: `get(url, params?)`, `post(url, body)`, `put(url, body)`, `del(url)`, `escapeHtml(str)`, `debounce(fn, wait)`
- `state.js` exports: `get(key)`, `set(key, value)`

- [ ] **Step 1: Create assets/js/api.js**

```js
// assets/js/api.js
const BASE = 'http://127.0.0.1:8989/api';

async function request(endpoint, options = {}) {
    const res = await fetch(`${BASE}${endpoint}`, {
        headers: {
            'Content-Type': 'application/json',
            'X-CureRx-Token': window.__crxToken ?? '',
        },
        ...options,
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({ message: res.statusText }));
        throw new Error(err.message ?? 'Request failed');
    }
    return res.json();
}

export const get  = (url, params) =>
    request(url + (params ? '?' + new URLSearchParams(params) : ''));
export const post = (url, body) =>
    request(url, { method: 'POST', body: JSON.stringify(body) });
export const put  = (url, body) =>
    request(url, { method: 'PUT',  body: JSON.stringify(body) });
export const del  = (url) =>
    request(url, { method: 'DELETE' });

export function escapeHtml(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

export function debounce(fn, wait) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
}
```

- [ ] **Step 2: Create assets/js/state.js**

```js
// assets/js/state.js
const _state = {
    selectedPatient:    null,
    settings:           null,
    medicinesMaster:    [],
    rxItems:            [],
    templates:          [],
    queueMonitorInterval: null,
    activeMedCategory:  'all',
    historyCurrentPage: 1,
    historyPageLimit:   10,
    historyTotalPages:  1,
};

export const get = (key) => _state[key];
export const set = (key, value) => { _state[key] = value; };
```

- [ ] **Step 3: Commit**

```bash
git add assets/js/api.js assets/js/state.js
git commit -m "feat(js): add api.js fetch wrapper and state.js singleton

api.js centralises all fetch() calls with X-CureRx-Token header injection.
state.js holds mutable app state via typed get/set; replaces closure-scoped
state object in app.js."
```

---

### Task 3: Create toast, autocomplete, modal components (#54 #55)

**Files:**
- Create: `assets/js/components/toast.js`
- Create: `assets/js/components/autocomplete.js`
- Create: `assets/js/components/modal.js`

**Interfaces:**
- `toast.js` exports: `showToast(message, type='info', duration=3000)`
- `autocomplete.js` exports: `createAutocomplete({ inputId, dropdownId, fetchFn, onSelect, minChars=2 })`
- `modal.js` exports: `openModal(id)`, `closeModal(id)`, `closeAllModals()`

- [ ] **Step 1: Create assets/js/components/ directory**

```bash
mkdir -p assets/js/components
```

- [ ] **Step 2: Create assets/js/components/toast.js**

```js
// assets/js/components/toast.js

let container;

function ensureContainer() {
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText =
            'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;';
        document.body.appendChild(container);
    }
}

export function showToast(message, type = 'info', duration = 3000) {
    ensureContainer();
    const colors = { info: '#3b82f6', success: '#22c55e', error: '#ef4444', warning: '#f59e0b' };
    const toast = document.createElement('div');
    toast.style.cssText =
        `background:${colors[type] ?? colors.info};color:#fff;padding:.75rem 1.25rem;` +
        `border-radius:.5rem;max-width:320px;font-size:.875rem;box-shadow:0 2px 8px rgba(0,0,0,.2);` +
        `opacity:1;transition:opacity .3s;`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}
```

- [ ] **Step 3: Create assets/js/components/autocomplete.js**

```js
// assets/js/components/autocomplete.js

export function createAutocomplete({ inputId, dropdownId, fetchFn, onSelect, minChars = 2 }) {
    const input    = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    if (!input || !dropdown) return;

    let timer;

    input.addEventListener('input', () => {
        clearTimeout(timer);
        if (input.value.length < minChars) { dropdown.hidden = true; return; }
        timer = setTimeout(async () => {
            try {
                const results = await fetchFn(input.value);
                renderDropdown(dropdown, results, onSelect, input);
            } catch {
                dropdown.hidden = true;
            }
        }, 250);
    });

    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && e.target !== input) {
            dropdown.hidden = true;
        }
    });
}

function renderDropdown(dropdown, results, onSelect, input) {
    dropdown.innerHTML = '';
    if (!results.length) { dropdown.hidden = true; return; }
    results.forEach((item) => {
        const li = document.createElement('li');
        li.textContent = item.label ?? item.name ?? JSON.stringify(item);
        li.addEventListener('mousedown', (e) => {
            e.preventDefault();
            onSelect(item);
            input.value = item.label ?? item.name ?? '';
            dropdown.hidden = true;
        });
        dropdown.appendChild(li);
    });
    dropdown.hidden = false;
}
```

- [ ] **Step 4: Create assets/js/components/modal.js**

```js
// assets/js/components/modal.js

export function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'flex';
}

export function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

export function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach(m => { m.style.display = 'none'; });
}
```

- [ ] **Step 5: Commit**

```bash
git add assets/js/components/
git commit -m "feat(js): add toast, autocomplete factory, modal components

toast.js replaces alert() with non-blocking notifications.
autocomplete.js factory eliminates the 3 duplicated typeahead implementations.
modal.js provides open/close helpers for overlay divs.

Closes #54
Closes #55"
```

---

### Task 4: Extract patients module (#31 partial)

**Files:**
- Create: `assets/js/modules/patients.js`

**Source functions in app.js (read the file to extract these):**
- `initPatientSearch()` — lines ~696–744: patient search typeahead for the prescription builder; refactor to use `createAutocomplete()`
- `selectPatient(p)` — lines ~745–799: sets state.selectedPatient, updates UI
- `loadRepeatLastRxButton(patientId)` — lines ~800–848: fetches last Rx for repeat button
- `loadPatientsList()` — lines ~1897–1971: loads patient directory tab
- Patient CRUD handlers in `initModals()` around patient form submit — lines ~1620–1665

**Interfaces:**
- Exports: `init()` — wires all patient events on DOMContentLoaded
- Imports: `* as api from '../api.js'`, `* as state from '../state.js'`, `{ showToast } from '../components/toast.js'`, `{ createAutocomplete } from '../components/autocomplete.js'`, `{ openModal, closeModal } from '../components/modal.js'`

- [ ] **Step 1: Read app.js lines 696–848 and 1897–1971 and the patient CRUD section**

Read `/Users/akash/Downloads/cureRXPro/assets/js/app.js` — focus on the functions listed above.

- [ ] **Step 2: Create assets/js/modules/patients.js**

```bash
mkdir -p assets/js/modules
```

Create the file with this structure:

```js
// assets/js/modules/patients.js
import * as api   from '../api.js';
import * as state from '../state.js';
import { showToast }          from '../components/toast.js';
import { createAutocomplete } from '../components/autocomplete.js';
import { openModal, closeModal } from '../components/modal.js';

export function init() {
    initPatientSearch();
    initPatientsList();
    initPatientCrud();
}

// --- extract initPatientSearch(), selectPatient(), loadRepeatLastRxButton() here ---
// --- extract loadPatientsList() here ---
// --- extract patient add/edit/delete handlers here ---
```

Fill in the actual function bodies by copying from app.js and adapting:
- Replace `fetch('api/patients.php...')` with `api.get('/patients.php', ...)` or `api.post('/patients.php', ...)`
- Replace `state.selectedPatient` (closure var) with `state.get('selectedPatient')` / `state.set('selectedPatient', p)`
- Replace `alert(...)` with `showToast(..., 'error')`
- Replace autocomplete inline code with `createAutocomplete({ ... })`
- Keep `escapeHtml()` usage but import it: `import { escapeHtml } from '../api.js'`

- [ ] **Step 3: Commit**

```bash
git add assets/js/modules/patients.js
git commit -m "refactor(js): extract patients module from app.js

Moves initPatientSearch, selectPatient, loadRepeatLastRxButton,
loadPatientsList and patient CRUD handlers into a focused ES module.
Patient autocomplete uses the shared factory component."
```

---

### Task 5: Extract queue module (#31 partial)

**Files:**
- Create: `assets/js/modules/queue.js`

**Source functions in app.js:**
- `addPatientToQueue(patientId)` — lines ~2220–2242
- `loadWaitingQueue()` — lines ~2243–2328
- `renderQueueMonitorData(data)` — lines ~2329–2394
- `loadQueueMonitorDashboard()` — lines ~2395–2406
- The interval setup for queue auto-refresh (uses `state.queueMonitorInterval`)

**Interfaces:**
- Exports: `init()`
- Imports: api, state, showToast, escapeHtml

- [ ] **Step 1: Read app.js lines 2220–2406**

- [ ] **Step 2: Create assets/js/modules/queue.js**

```js
// assets/js/modules/queue.js
import * as api   from '../api.js';
import * as state from '../state.js';
import { showToast } from '../components/toast.js';
import { escapeHtml } from '../api.js';

export function init() {
    // Wire queue monitor refresh button
    const btn = document.getElementById('btn-refresh-queue-monitor');
    if (btn) btn.addEventListener('click', loadQueueMonitorDashboard);

    loadWaitingQueue();
}

// --- extract all queue functions here ---
// Replace state.queueMonitorInterval with state.get/set('queueMonitorInterval')
// Replace alert() with showToast()
// Replace fetch() with api.get/post/put/del()
```

- [ ] **Step 3: Commit**

```bash
git add assets/js/modules/queue.js
git commit -m "refactor(js): extract queue module from app.js

Moves loadWaitingQueue, addPatientToQueue, renderQueueMonitorData,
loadQueueMonitorDashboard into a focused ES module."
```

---

### Task 6: Extract billing module (#31 partial)

**Files:**
- Create: `assets/js/modules/billing.js`

**Source functions in app.js:**
- `initChannelingBilling()` — lines ~2407–2584: includes the patient autocomplete for billing; refactor to `createAutocomplete()`
- `loadChannelingBillsToday()` — lines ~2585–2673
- `printChannelingReceipt(bill)` — lines ~2674–2763
- `toggleBillingUI(show)` — lines ~448–676 (or wherever this helper lives)

**Interfaces:**
- Exports: `init()`
- Imports: api, state, showToast, escapeHtml, createAutocomplete

- [ ] **Step 1: Read app.js lines 448–676 and 2407–2763**

- [ ] **Step 2: Create assets/js/modules/billing.js**

```js
// assets/js/modules/billing.js
import * as api   from '../api.js';
import * as state from '../state.js';
import { showToast } from '../components/toast.js';
import { escapeHtml } from '../api.js';
import { createAutocomplete } from '../components/autocomplete.js';

export function init() {
    initChannelingBilling();
}

// --- extract all billing functions here ---
// The patient autocomplete in initChannelingBilling() is the duplicate of initPatientSearch()
// Replace it with createAutocomplete({ ... }) using the same config shape as patients module
```

- [ ] **Step 3: Commit**

```bash
git add assets/js/modules/billing.js
git commit -m "refactor(js): extract billing module from app.js

Moves initChannelingBilling, loadChannelingBillsToday, printChannelingReceipt
into a focused ES module. Billing patient search uses the shared autocomplete factory."
```

---

### Task 7: Extract medicines module with pagination (#31 #59)

**Files:**
- Create: `assets/js/modules/medicines.js`

**Source functions in app.js:**
- `initCategoryFilterChips()` — lines ~1972–1992
- `loadMedicinesList(category)` — lines ~1993–2031: currently fetches with no limit — update to pass `limit` and `offset` query params for pagination
- `loadDoctorsList()` — lines ~2032–2139: move here (doctors are managed from medicines/settings tab context — or move to settings module; your judgment, keeping here is fine as it's in the same tab area)
- `initDoctorManagement()` — lines ~2140–2219

**Pagination:** The API endpoint now supports `limit` and `offset`. Add pagination controls to the medicines tab. Load 50 per page, show prev/next buttons. Wire them in `init()`.

**Interfaces:**
- Exports: `init()`
- Imports: api, state, showToast, escapeHtml

- [ ] **Step 1: Read app.js lines 1972–2219**

- [ ] **Step 2: Create assets/js/modules/medicines.js**

```js
// assets/js/modules/medicines.js
import * as api   from '../api.js';
import * as state from '../state.js';
import { showToast } from '../components/toast.js';
import { escapeHtml } from '../api.js';

let currentPage  = 0;
const PAGE_SIZE  = 50;

export function init() {
    initCategoryFilterChips();
    loadMedicinesList();
    initDoctorManagement();
    wirePaginationButtons();
}

function wirePaginationButtons() {
    const prev = document.getElementById('btn-med-prev');
    const next = document.getElementById('btn-med-next');
    if (prev) prev.addEventListener('click', () => { if (currentPage > 0) { currentPage--; loadMedicinesList(); } });
    if (next) next.addEventListener('click', () => { currentPage++; loadMedicinesList(); });
}

// --- extract all medicine + doctor functions here ---
// loadMedicinesList: pass ?limit=50&offset=currentPage*50 to api.get()
// Replace alert() with showToast()
// Replace fetch() with api.get/post/put/del()
```

Note: If the medicines tab HTML does not already have `btn-med-prev` / `btn-med-next` buttons, add them in the `views/tabs/tab-medicines.php` partial. Keep it simple: `<button id="btn-med-prev">Prev</button> <button id="btn-med-next">Next</button>`.

- [ ] **Step 3: Commit**

```bash
git add assets/js/modules/medicines.js
git commit -m "refactor(js): extract medicines module with pagination

Medicines list now uses limit/offset pagination (50 per page) instead of
loading all records. Prev/Next controls added to tab-medicines partial."
```

---

### Task 8: Extract prescriptions module + draft auto-save (#31 #52)

**Files:**
- Create: `assets/js/modules/prescriptions.js`

**Source functions in app.js:**
- `initDiseaseChips()` — lines ~60–75
- `initKeyboardShortcuts()` — lines ~108–188 (keyboard shortcuts for Rx table)
- `initPediatricCalculator()` — lines ~189–310
- `initRxTable()` — lines ~898–911: Rx table init
- `addRxRow(data)` — lines ~912–1073: adds a medicine row to the Rx table
- `recalculateGrandTotal()` — lines ~1074–1182
- `renderPrintDocument(rx, paperSize, printMode)` — lines ~1183–1374
- `openPrintPreviewModal(rxId)` — lines ~1375–1403
- `refreshPrintView()`, `updatePrintModeButtons()`, `updatePaperSizeButtons()` — lines ~1404–1595
- `clearPrescriptionForm()` — lines ~2764–2806
- The prescription save handler (inside initModals or the save button listener, search for `api/prescriptions.php` POST)
- `loadRepeatLastRxButton` consumers

**Draft auto-save (#52):**

Add after the Rx form wires up:

```js
const DRAFT_KEY = 'rx_draft';

function saveDraft() {
    const draft = {
        patientId: state.get('selectedPatient')?.id ?? null,
        rxItems:   state.get('rxItems'),
        diagnosis: document.getElementById('rx-diagnosis')?.value ?? '',
        followUp:  document.getElementById('rx-follow-up')?.value ?? '',
    };
    localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
}

function restoreDraft() {
    const raw = localStorage.getItem(DRAFT_KEY);
    if (!raw) return;
    try {
        const draft = JSON.parse(raw);
        if (draft.rxItems?.length) {
            draft.rxItems.forEach(item => addRxRow(item));
            showToast('Unsaved prescription draft restored.', 'info');
        }
    } catch { /* ignore corrupt draft */ }
}

function clearDraft() {
    localStorage.removeItem(DRAFT_KEY);
}
```

Wire debounced saveDraft to all prescription form field `input` events. Call `restoreDraft()` in `init()`. Call `clearDraft()` after successful save.

**Interfaces:**
- Exports: `init()`
- Imports: api, state, showToast, escapeHtml, debounce, openModal, closeModal

- [ ] **Step 1: Read app.js lines 60–75, 108–310, 898–1595, 2764–2806**

- [ ] **Step 2: Create assets/js/modules/prescriptions.js**

```js
// assets/js/modules/prescriptions.js
import * as api   from '../api.js';
import * as state from '../state.js';
import { showToast }         from '../components/toast.js';
import { escapeHtml, debounce } from '../api.js';
import { openModal, closeModal } from '../components/modal.js';

const DRAFT_KEY = 'rx_draft';

export function init() {
    initDiseaseChips();
    initKeyboardShortcuts();
    initPediatricCalculator();
    initRxTable();
    restoreDraft();
    wireDraftAutoSave();
    loadTemplates();
}

// --- paste saveDraft, restoreDraft, clearDraft from above ---
// --- extract all prescription functions from app.js ---
// Replace alert() with showToast()
// Replace fetch() with api.get/post/put/del()
// Replace state.rxItems with state.get/set('rxItems')
// Replace state.templates with state.get/set('templates')
```

- [ ] **Step 3: Commit**

```bash
git add assets/js/modules/prescriptions.js
git commit -m "refactor(js): extract prescriptions module from app.js"
```

- [ ] **Step 4: Commit draft auto-save separately**

After the module extraction is done and working, add the draft auto-save wiring:

```bash
git add assets/js/modules/prescriptions.js
git commit -m "feat(prescriptions): add localStorage draft auto-save on field change

Debounced save on any prescription field input. Draft restored on init.
Draft cleared on successful Rx save.

Closes #52"
```

---

### Task 9: Extract history, templates, settings, stats modules (#31)

**Files:**
- Create: `assets/js/modules/history.js`
- Create: `assets/js/modules/templates.js`
- Create: `assets/js/modules/settings.js`
- Create: `assets/js/modules/stats.js`

**Source functions:**

**history.js:**
- `loadPrescriptionsHistory(page)` — lines ~1688–1745
- `updateHistoryPaginationUI(total, page, totalPages)` — lines ~1746–1764
- `openPatientHistoryModal(patientId)` — lines ~1765–1896

**templates.js:**
- `loadTemplates()` — lines ~849–862
- `renderTemplateChips(templates)` — lines ~863–888
- `applyTemplate(items)` — lines ~889–897

**settings.js:**
- `loadSettings()` — lines ~352–365
- `populateSettingsForm(s)` — lines ~366–447
- `syncOnlineSubscription()` — lines ~2807–2846
- The settings form save handler (search for settings.php POST)
- Backup/restore handlers (search for backup.php)

**stats.js:**
- `loadStats()` — lines ~677–695

**Interfaces:**
- Each exports `init()`
- All import api, state, showToast, escapeHtml as needed

- [ ] **Step 1: Read the relevant line ranges from app.js**

Read `/Users/akash/Downloads/cureRXPro/assets/js/app.js` lines 352–695, 849–897, 1688–1896, 2807–2846.

- [ ] **Step 2: Create all four modules**

For each module:
```js
// assets/js/modules/{name}.js
import * as api   from '../api.js';
import * as state from '../state.js';
import { showToast } from '../components/toast.js';
import { escapeHtml } from '../api.js';

export function init() {
    // wire events + initial load
}

// ... extracted functions ...
```

Apply the same adaptation rules: fetch() → api.*, alert() → showToast(), state vars → state.get/set().

- [ ] **Step 3: Commit**

```bash
git add assets/js/modules/history.js assets/js/modules/templates.js \
        assets/js/modules/settings.js assets/js/modules/stats.js
git commit -m "refactor(js): extract history, templates, settings, stats modules

All four modules now export init() and use the shared api/state/toast
infrastructure. Removes the corresponding logic from app.js scope."
```

---

### Task 10: Wire main.js, update index.php, delete app.js (#31)

**Files:**
- Create: `assets/js/main.js`
- Modify: `index.php` (swap script tag)
- Delete: `assets/js/app.js`

**Interfaces:**
- `main.js` is the sole entry point; imports and inits all modules
- `index.php` uses `<script type="module" src="assets/js/main.js">` instead of `app.js`

- [ ] **Step 1: Create assets/js/main.js**

```js
// assets/js/main.js
import { init as initPatients }      from './modules/patients.js';
import { init as initQueue }         from './modules/queue.js';
import { init as initBilling }       from './modules/billing.js';
import { init as initMedicines }     from './modules/medicines.js';
import { init as initPrescriptions } from './modules/prescriptions.js';
import { init as initHistory }       from './modules/history.js';
import { init as initTemplates }     from './modules/templates.js';
import { init as initSettings }      from './modules/settings.js';
import { init as initStats }         from './modules/stats.js';
import { closeAllModals }            from './components/modal.js';

document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initStats();
    initSettings();
    initPatients();
    initPrescriptions();
    initQueue();
    initBilling();
    initMedicines();
    initHistory();
    initTemplates();
});

function initNavigation() {
    document.querySelectorAll('.nav-item[data-tab]').forEach(link => {
        link.addEventListener('click', () => {
            document.querySelectorAll('.nav-item').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            link.classList.add('active');
            const tab = document.getElementById(link.dataset.tab);
            if (tab) tab.classList.add('active');
            closeAllModals();
        });
    });
}
```

- [ ] **Step 2: Update index.php script tag**

In `index.php`, replace:
```php
<script src="assets/js/app.js"></script>
```
with:
```php
<script type="module" src="assets/js/main.js"></script>
```

- [ ] **Step 3: Delete assets/js/app.js**

```bash
git rm assets/js/app.js
```

- [ ] **Step 4: Verify no remaining references to app.js**

```bash
grep -r 'app\.js' index.php views/ assets/js/
```

Expected: zero matches.

- [ ] **Step 5: Test navigation manually**

If running the app, click through tabs. The tab switching must work. If any module `init()` throws an error, check browser console and fix the import.

- [ ] **Step 6: Commit**

```bash
git add assets/js/main.js index.php && git rm assets/js/app.js
git commit -m "refactor(js): wire all modules in main.js; delete legacy app.js

Entry point is now assets/js/main.js loaded as a native ES module.
app.js (2868 lines) removed. Navigation wired in main.js.

Closes #31"
```

---

## Phase 4 Complete

After all 10 tasks (11 commits):

Issues closed: #30 #31 #52 #54 #55

**Next:** `docs/superpowers/plans/2026-09-04-phase5-testing.md`
