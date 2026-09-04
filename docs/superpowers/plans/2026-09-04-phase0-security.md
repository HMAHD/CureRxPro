# Phase 0 — Security Triage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close all 7 critical security issues (#26 #27 #28 #29 #44 #45 #57) with 9 atomic commits.

**Architecture:** Direct file edits — no new abstractions introduced yet. Security fixes are surgical: delete bad files, tighten auth, replace the license check, fix XSS, encrypt backups.

**Tech Stack:** PHP 8, JavaScript (ES5 in existing app.js), Node.js/Electron main.js, OpenSSL via PHP

**Spec:** `docs/superpowers/specs/2026-09-04-swe-remediation-design.md` §4

## Global Constraints

- Branch: `refactor/swe-remediation` (already exists)
- No watermarks, no AI attribution in commits, code, or comments
- One logical change per commit; `Closes #N` in commit body
- Do not touch CSS, print.css, or Python pipeline scripts
- Match existing code style in each file edited

---

### Task 1: Exclude secrets from git (#29)

**Files:**
- Modify: `.gitignore`

**Interfaces:**
- Produces: secrets no longer tracked by git

- [ ] **Step 1: Read current .gitignore**

```bash
cat .gitignore
```

- [ ] **Step 2: Add secret file entries**

Append to `.gitignore`:
```
# Secrets — never commit
api_secret.key
config/db_config.json
.schema_initialized
```

- [ ] **Step 3: Verify they are not already tracked**

```bash
git ls-files api_secret.key config/db_config.json .schema_initialized
```

If any are tracked, untrack them:
```bash
git rm --cached api_secret.key config/db_config.json .schema_initialized 2>/dev/null || true
```

- [ ] **Step 4: Commit**

```bash
git add .gitignore
git commit -m "$(cat <<'EOF'
chore(gitignore): exclude api_secret.key, db_config.json, and schema flag

Closes #29
EOF
)"
```

---

### Task 2: Remove debug endpoints from web root (#26)

**Files:**
- Delete: `simulate_subscription.php`
- Delete: `clear_database.php`
- Delete: `admin_license_generator.php`

- [ ] **Step 1: Confirm files exist**

```bash
ls simulate_subscription.php clear_database.php admin_license_generator.php
```

- [ ] **Step 2: Delete simulate_subscription.php**

```bash
git rm simulate_subscription.php
git commit -m "$(cat <<'EOF'
fix(security): remove simulate_subscription.php debug endpoint

Unauthenticated endpoint that allowed arbitrary subscription state
manipulation in production. No legitimate use case in shipped binary.

Closes #26
EOF
)"
```

- [ ] **Step 3: Delete clear_database.php**

```bash
git rm clear_database.php
git commit -m "$(cat <<'EOF'
fix(security): remove clear_database.php admin utility from web root

Unauthenticated endpoint capable of wiping the entire patient database.
Accessible to any process on the machine able to reach port 8989.

Closes #26
EOF
)"
```

- [ ] **Step 4: Delete admin_license_generator.php**

```bash
git rm admin_license_generator.php
git commit -m "$(cat <<'EOF'
fix(security): remove admin_license_generator.php from web root

Exposed license generation logic to any local caller. License signing
must happen server-side only.

Closes #26
EOF
)"
```

---

### Task 3: Lock auto-update to github.com releases (#27)

**Files:**
- Modify: `main.js` lines ~333–401 (`ipcMain.on('start-auto-update', ...)`)
- Modify: `preload.js` (`startAutoUpdate` exposure)

**Interfaces:**
- Consumes: version tag string from renderer (e.g. `v1.4.2`) instead of full URL
- Produces: download URL constructed in main process only, locked to `github.com/HMAHD/CureRxPro`

- [ ] **Step 1: Read main.js lines 332-401 and preload.js**

Read both files to understand current IPC contract before changing it.

- [ ] **Step 2: Replace the IPC handler in main.js**

Find the block starting with `ipcMain.on('start-auto-update', (event, downloadUrl) => {` and replace it with:

```js
// IPC Handler: renderer sends a version tag; main constructs and validates the URL
ipcMain.on('start-auto-update', (event, versionTag) => {
  const fs = require('fs');
  const https = require('https');
  const { exec } = require('child_process');

  // Validate version tag format (e.g. v1.4.2) — reject anything else
  if (typeof versionTag !== 'string' || !/^v\d+\.\d+\.\d+$/.test(versionTag)) {
    event.reply('auto-update-error', 'Invalid version tag');
    return;
  }

  // URL is constructed here — renderer cannot influence the host
  const downloadUrl = `https://github.com/HMAHD/CureRxPro/releases/download/${versionTag}/CureRx_Pro_Setup_Update.exe`;

  const tempDir = app.getPath('temp');
  const installerPath = path.join(tempDir, 'CureRx_Pro_Setup_Update.exe');

  function startDownload(targetUrl) {
    // Only follow redirects within github.com / objects.githubusercontent.com
    const allowedHosts = ['github.com', 'objects.githubusercontent.com'];
    let parsedHost;
    try { parsedHost = new URL(targetUrl).hostname; } catch (_) { parsedHost = ''; }
    if (!allowedHosts.includes(parsedHost)) {
      event.reply('auto-update-error', 'Redirect to untrusted host blocked');
      return;
    }

    const file = fs.createWriteStream(installerPath);
    https.get(targetUrl, (response) => {
      if (response.statusCode >= 300 && response.statusCode < 400 && response.headers.location) {
        file.close();
        startDownload(response.headers.location);
        return;
      }
      if (response.statusCode !== 200) {
        file.close();
        fs.unlink(installerPath, () => {});
        event.reply('auto-update-error', `Server returned status: ${response.statusCode}`);
        return;
      }
      const totalBytes = parseInt(response.headers['content-length'], 10) || 0;
      let downloadedBytes = 0;
      response.on('data', (chunk) => {
        downloadedBytes += chunk.length;
        file.write(chunk);
        if (totalBytes > 0) {
          event.reply('auto-update-progress', Math.round((downloadedBytes / totalBytes) * 100));
        }
      });
      response.on('end', () => {
        file.end(() => {
          event.reply('auto-update-progress', 100);
          setTimeout(() => {
            exec(`"${installerPath}" /S`, (err) => {
              if (err) console.error('Failed to run silent installer:', err);
            });
            app.quit();
          }, 1000);
        });
      });
    }).on('error', (err) => {
      file.close();
      fs.unlink(installerPath, () => {});
      event.reply('auto-update-error', err.message);
    });
  }

  fs.unlink(installerPath, () => { startDownload(downloadUrl); });
});
```

- [ ] **Step 3: Update preload.js to send version tag, not URL**

Find the `startAutoUpdate` line in preload.js and change:
```js
// BEFORE
startAutoUpdate: (url) => ipcRenderer.send('start-auto-update', url),
// AFTER
startAutoUpdate: (versionTag) => ipcRenderer.send('start-auto-update', versionTag),
```

- [ ] **Step 4: Commit**

```bash
git add main.js preload.js
git commit -m "$(cat <<'EOF'
fix(security): lock auto-update downloads to github.com releases only

Renderer previously sent an arbitrary URL which main.js downloaded and
executed as a privileged installer. Main now constructs the URL from a
version tag and validates all redirect targets against an allowlist.

Closes #27
EOF
)"
```

---

### Task 4: Replace trivial license check with HMAC-SHA256 (#28)

**Files:**
- Modify: `api/settings.php` (validation logic around line 50)

**Interfaces:**
- Consumes: `LICENSE_KEY` field from POST body, `api_secret.key` file on disk
- Produces: `hash_equals()`-safe HMAC validation; old CRX-prefix check removed

- [ ] **Step 1: Read api/settings.php**

Read the full file to understand current structure before editing.

- [ ] **Step 2: Add HMAC validation function and replace the check**

Find the section that does `str_contains($upperKey, 'CRX')` (or equivalent trivial check) and replace the license validation block with:

```php
function validate_license_key(string $key, string $secret): bool {
    // Expected format: CRX-{payload}-{hmac}
    // payload = base64url(plan|expiry_unix)
    if (!str_starts_with($key, 'CRX-')) {
        return false;
    }
    $parts = explode('-', $key, 3); // ['CRX', payload, hmac]
    if (count($parts) !== 3) {
        return false;
    }
    [, $payload, $providedHmac] = $parts;
    $signingKey = hash('sha256', $secret, true);
    $expectedHmac = hash_hmac('sha256', $payload, $signingKey);
    if (!hash_equals($expectedHmac, $providedHmac)) {
        return false;
    }
    // Decode payload: plan|expiry_unix
    $decoded = base64_decode(strtr($payload, '-_', '+/'));
    if ($decoded === false) {
        return false;
    }
    [$plan, $expiry] = explode('|', $decoded, 2) + ['', '0'];
    if ((int)$expiry !== 0 && (int)$expiry < time()) {
        return false; // expired
    }
    return in_array($plan, ['starter', 'pro', 'clinic'], true);
}

// Load the installation secret
$secretFile = getenv('USER_DATA_PATH')
    ? getenv('USER_DATA_PATH') . '/api_secret.key'
    : __DIR__ . '/../api_secret.key';
$installSecret = file_exists($secretFile) ? trim(file_get_contents($secretFile)) : '';

// Replace the old string-match check:
if (!validate_license_key($licenseKey, $installSecret)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired license key']);
    exit;
}
```

- [ ] **Step 3: Verify the old trivial check is gone**

```bash
grep -n 'CRX' api/settings.php
```

Expected: only the `'CRX-'` prefix in `validate_license_key`. No bare `str_contains($upperKey, 'CRX')`.

- [ ] **Step 4: Commit**

```bash
git add api/settings.php
git commit -m "$(cat <<'EOF'
fix(security): replace CRX string-match license check with HMAC-SHA256

The old check accepted any string containing 'CRX' as a valid license.
Replaced with HMAC-SHA256 signed keys (format: CRX-{payload}-{hmac})
verified with hash_equals() to prevent timing attacks.

Closes #28
EOF
)"
```

---

### Task 5: Remove API token from query-string fallback (#45)

**Files:**
- Modify: `config/auth_check.php`

- [ ] **Step 1: Read config/auth_check.php**

Read to find the `$_REQUEST['api_token']` or `$_GET['api_token']` fallback.

- [ ] **Step 2: Remove query-string fallback**

Find the token extraction logic. It likely looks like:
```php
$token = $_SERVER['HTTP_X_CURERX_TOKEN'] ?? $_REQUEST['api_token'] ?? '';
```

Change to header-only:
```php
$token = $_SERVER['HTTP_X_CURERX_TOKEN'] ?? '';
```

Remove any block that reads `$_GET['api_token']` or `$_REQUEST['api_token']`.

- [ ] **Step 3: Verify**

```bash
grep -n 'api_token\|REQUEST\[' config/auth_check.php
```

Expected: zero matches.

- [ ] **Step 4: Commit**

```bash
git add config/auth_check.php
git commit -m "$(cat <<'EOF'
fix(security): remove API token query-string fallback from auth_check.php

Tokens passed via URL query parameters are written to access logs and
browser history. Only the X-CureRx-Token header is now accepted.

Closes #45
EOF
)"
```

---

### Task 6: Apply escapeHtml consistently in app.js (#44)

**Files:**
- Modify: `assets/js/app.js`

- [ ] **Step 1: Find all template literals that render user data without escaping**

```bash
grep -n 'innerHTML\s*[+=]\|\.innerHTML\s*=' assets/js/app.js | head -60
```

Also search for interpolated values not wrapped in escapeHtml:
```bash
grep -n '\${[^}]*name\|${[^}]*address\|${[^}]*phone\|${[^}]*note\|${[^}]*instruction\|${[^}]*medicine' assets/js/app.js | grep -v 'escapeHtml'
```

- [ ] **Step 2: Ensure escapeHtml function exists and is correct**

Search for its definition:
```bash
grep -n 'function escapeHtml' assets/js/app.js
```

If not present or incomplete, add/replace at the top of the file:
```js
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
```

- [ ] **Step 3: Wrap every unescaped user-data interpolation**

For each template literal line found in Step 1, wrap the variable:
```js
// BEFORE
`<td>${patient.name}</td>`
// AFTER
`<td>${escapeHtml(patient.name)}</td>`
```

Apply consistently for: `name`, `address`, `phone`, `email`, `note`, `instruction`, `medicine_name`, `clinic_name`, `doctor_name`, `strength`, `dosage`, `duration`.

- [ ] **Step 4: Verify no unescaped interpolations remain for user fields**

```bash
grep -n '\${[a-z][^}]*\.\(name\|address\|phone\|note\|instruction\|medicine_name\|clinic_name\)}' assets/js/app.js | grep -v escapeHtml
```

Expected: zero matches (or only escapeHtml-wrapped ones).

- [ ] **Step 5: Commit**

```bash
git add assets/js/app.js
git commit -m "$(cat <<'EOF'
fix(security): apply escapeHtml consistently across all app.js template literals

User-supplied fields (name, address, instruction, medicine_name, etc.)
were interpolated raw into innerHTML in several places, enabling stored XSS.
All template literal interpolations of user data now go through escapeHtml().

Closes #44
EOF
)"
```

---

### Task 7: Encrypt backup export with AES-256-CBC (#57)

**Files:**
- Modify: `api/backup.php`

- [ ] **Step 1: Read api/backup.php**

Read the full file to understand export and import flow.

- [ ] **Step 2: Add encryption helpers and update export**

Add two helper functions and update the export path. Find the export section (where JSON is output) and wrap it:

```php
function backup_encryption_key(): string {
    $secretFile = (getenv('USER_DATA_PATH') ?: __DIR__ . '/..') . '/api_secret.key';
    $secret = file_exists($secretFile) ? trim(file_get_contents($secretFile)) : 'default-insecure';
    return hash('sha256', $secret, true); // 32 raw bytes for AES-256
}

function encrypt_backup(string $plaintext): string {
    $key = backup_encryption_key();
    $iv  = random_bytes(16);
    $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

function decrypt_backup(string $ciphertext): string|false {
    $key  = backup_encryption_key();
    $raw  = base64_decode($ciphertext);
    if (strlen($raw) < 17) return false;
    $iv     = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    return openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
}
```

In the export section, replace direct JSON output:
```php
// BEFORE
header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d') . '.json"');
header('Content-Type: application/json');
echo json_encode($exportData);

// AFTER
$json      = json_encode($exportData);
$encrypted = encrypt_backup($json);
header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d') . '.crxbak"');
header('Content-Type: application/octet-stream');
echo $encrypted;
```

In the import section, add decryption before JSON decode:
```php
// BEFORE
$importData = json_decode(file_get_contents($_FILES['backup_file']['tmp_name']), true);

// AFTER
$raw = file_get_contents($_FILES['backup_file']['tmp_name']);
// Support both legacy .json and new .crxbak format
$decoded = json_decode($raw, true);
if ($decoded === null) {
    $plaintext = decrypt_backup($raw);
    if ($plaintext === false) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Cannot decrypt backup file. Wrong installation key?']);
        exit;
    }
    $decoded = json_decode($plaintext, true);
}
$importData = $decoded;
```

- [ ] **Step 3: Verify openssl is available in the PHP build**

```bash
php -r "echo function_exists('openssl_encrypt') ? 'ok' : 'missing';"
```

Expected: `ok`

- [ ] **Step 4: Commit**

```bash
git add api/backup.php
git commit -m "$(cat <<'EOF'
fix(security): encrypt database backup export with AES-256-CBC

Backup files previously exported the full patient database as plain JSON.
Export now encrypts with AES-256-CBC using a key derived from api_secret.key.
Import supports both new .crxbak format and legacy .json for migration.

Closes #57
EOF
)"
```

---

## Phase 0 Complete

After all 7 tasks (9 commits) above:

```bash
git log --oneline -10
```

Expected commits (newest first):
```
fix(security): encrypt database backup export with AES-256-CBC
fix(security): remove API token query-string fallback from auth_check.php
fix(security): apply escapeHtml consistently across all app.js template literals
fix(security): replace CRX string-match license check with HMAC-SHA256
fix(security): lock auto-update downloads to github.com releases only
fix(security): remove admin_license_generator.php from web root
fix(security): remove clear_database.php admin utility from web root
fix(security): remove simulate_subscription.php debug endpoint
chore(gitignore): exclude api_secret.key, db_config.json, and schema flag
```

Issues closed: #26, #27, #28, #29, #44, #45, #57

**Next:** `docs/superpowers/plans/2026-09-04-phase1-infrastructure.md`
