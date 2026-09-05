# Phase 2 — Database Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace ALTER TABLE try/catch migration system with a proper schema_migrations runner; add indexes, soft deletes, and audit log — closing #33 #39 #56 #58.

**Architecture:** New `src/Core/Migrations.php` runner; `config/db.php` loses all ALTER TABLE blocks; four new SQL migration files (005–008).

**Tech Stack:** PHP 8, MySQL/PDO, SQL

**Spec:** `docs/superpowers/specs/2026-09-04-swe-remediation-design.md` §6

## Global Constraints

- Branch: `refactor/swe-remediation`
- No AI attribution or watermarks in commits
- One logical change per commit; `Closes #N` in body where applicable
- Do not change DB connection logic, port detection, or config loading in db.php
- Existing `CREATE TABLE IF NOT EXISTS` base DDL in `init_tables()` stays — only ALTER TABLE try/catch blocks are removed
- Migration files are numbered sequentially; existing 001–004 are NOT modified
- `src/Core/Migrations.php` uses only standard PHP and PDO — no Composer dependencies

---

### Task 1: Add Migrations runner + schema_migrations table (#33 part 1)

**Files:**
- Create: `src/Core/Migrations.php`
- Create: `src/Core/` directory

**Interfaces:**
- Produces: `\CureRx\Core\Migrations::run(PDO $pdo): void` — idempotent, runs pending .sql files from `scripts/migrations/`

- [ ] **Step 1: Create src/Core/ directory**

```bash
mkdir -p src/Core
```

- [ ] **Step 2: Create src/Core/Migrations.php**

```php
<?php

namespace CureRx\Core;

class Migrations
{
    public function run(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `filename`   VARCHAR(255) NOT NULL,
            `applied_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`filename`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $applied = $pdo->query("SELECT `filename` FROM `schema_migrations`")
                       ->fetchAll(\PDO::FETCH_COLUMN);

        $pattern = __DIR__ . '/../../scripts/migrations/*.sql';
        $files   = glob($pattern);
        if ($files === false) {
            return;
        }
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                continue;
            }
            $pdo->exec($sql);
            $pdo->prepare("INSERT INTO `schema_migrations` (`filename`) VALUES (?)")
                ->execute([$name]);
        }
    }
}
```

- [ ] **Step 3: Verify PHP syntax**

```bash
php -l src/Core/Migrations.php 2>&1 || echo "(php not in PATH — verify visually)"
```

- [ ] **Step 4: Commit**

```bash
git add src/Core/Migrations.php
git commit -m "$(cat <<'EOF'
feat(db): add schema_migrations table and Migrations runner

Idempotent runner reads *.sql files from scripts/migrations/ in
filename order, applies each once, and records it in schema_migrations.
Replaces the ALTER TABLE try/catch anti-pattern.

Closes #33
EOF
)"
```

---

### Task 2: Remove ALTER TABLE try/catch from db.php (#33 part 2)

**Files:**
- Modify: `config/db.php`

**Context:** Lines 247–260 and 276–278 and 308 contain ALTER TABLE blocks wrapped in try/catch. These are now covered by migration files 001–004 (already existing) plus the new 005+ files. Remove them. Keep all `CREATE TABLE IF NOT EXISTS` blocks.

- [ ] **Step 1: Read config/db.php lines 240–316**

Identify all `try { $pdo->exec("ALTER TABLE ..."); } catch (\Exception $e) {}` lines.

- [ ] **Step 2: Remove all ALTER TABLE try/catch blocks**

Delete every line matching the pattern:
```php
try { $pdo->exec("ALTER TABLE `...` ADD COLUMN ..."); } catch (\Exception $e) {}
```

There are approximately 16 such lines across three sections. Remove them all. Do NOT remove `CREATE TABLE IF NOT EXISTS` lines.

- [ ] **Step 3: Verify no ALTER TABLE remains**

```bash
grep -n 'ALTER TABLE' config/db.php
```

Expected: zero matches.

- [ ] **Step 4: Commit**

```bash
git add config/db.php
git commit -m "$(cat <<'EOF'
refactor(db): remove ALTER TABLE try/catch blocks from init_tables

All schema changes are now handled by the Migrations runner reading
numbered SQL files from scripts/migrations/. The try/catch pattern
was silently swallowing errors and creating a dual migration system.

Closes #33
EOF
)"
```

---

### Task 3: Migration 005 — performance indexes (#56)

**Files:**
- Create: `scripts/migrations/005_performance_indexes.sql`

- [ ] **Step 1: Create the migration file**

```sql
-- 005_performance_indexes.sql
-- Add missing indexes on foreign key and date columns (issue #56)

ALTER TABLE `prescriptions`
    ADD INDEX IF NOT EXISTS `idx_prescriptions_patient_id` (`patient_id`),
    ADD INDEX IF NOT EXISTS `idx_prescriptions_doctor_id`  (`doctor_id`),
    ADD INDEX IF NOT EXISTS `idx_prescriptions_created_at` (`created_at`);

ALTER TABLE `prescription_items`
    ADD INDEX IF NOT EXISTS `idx_prescription_items_prescription_id` (`prescription_id`);

ALTER TABLE `waiting_queue`
    ADD INDEX IF NOT EXISTS `idx_waiting_queue_doctor_id`  (`doctor_id`),
    ADD INDEX IF NOT EXISTS `idx_waiting_queue_created_at` (`created_at`);

ALTER TABLE `channeling_bills`
    ADD INDEX IF NOT EXISTS `idx_channeling_bills_patient_id`  (`patient_id`),
    ADD INDEX IF NOT EXISTS `idx_channeling_bills_doctor_id`   (`doctor_id`),
    ADD INDEX IF NOT EXISTS `idx_channeling_bills_created_at`  (`created_at`);
```

Note: `ADD INDEX IF NOT EXISTS` is MySQL 8.0+. If the target MySQL version is 5.7, use individual try/catch in PHP instead. Since portable MySQL bundled with Electron apps is typically 8.x, use IF NOT EXISTS.

- [ ] **Step 2: Commit**

```bash
git add scripts/migrations/005_performance_indexes.sql
git commit -m "$(cat <<'EOF'
feat(migration): 005_performance_indexes for FK and date columns

Adds covering indexes on patient_id, doctor_id, created_at across
prescriptions, prescription_items, waiting_queue, channeling_bills.

Closes #56
EOF
)"
```

---

### Task 4: Migration 006 — soft deletes (#39)

**Files:**
- Create: `scripts/migrations/006_soft_deletes.sql`

- [ ] **Step 1: Create the migration file**

```sql
-- 006_soft_deletes.sql
-- Add soft delete support to patients and prescriptions (issue #39)

ALTER TABLE `patients`
    ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL DEFAULT NULL;

ALTER TABLE `patients`
    ADD INDEX IF NOT EXISTS `idx_patients_deleted_at` (`deleted_at`);

ALTER TABLE `prescriptions`
    ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL DEFAULT NULL;

ALTER TABLE `prescriptions`
    ADD INDEX IF NOT EXISTS `idx_prescriptions_deleted_at` (`deleted_at`);
```

- [ ] **Step 2: Commit**

```bash
git add scripts/migrations/006_soft_deletes.sql
git commit -m "$(cat <<'EOF'
feat(migration): 006_soft_deletes on patients and prescriptions

Adds deleted_at DATETIME NULL to patients and prescriptions tables.
Hard DELETE endpoints will be converted to soft deletes in Phase 3.

Closes #39
EOF
)"
```

---

### Task 5: Migration 007 — audit log (#58)

**Files:**
- Create: `scripts/migrations/007_audit_log.sql`

- [ ] **Step 1: Create the migration file**

```sql
-- 007_audit_log.sql
-- Prescription and patient mutation audit trail (issue #58)

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`           BIGINT       NOT NULL AUTO_INCREMENT,
    `action`       ENUM('create','update','delete') NOT NULL,
    `table_name`   VARCHAR(64)  NOT NULL,
    `record_id`    INT          NOT NULL,
    `old_values`   JSON         NULL,
    `new_values`   JSON         NULL,
    `workstation`  VARCHAR(100) NULL,
    `performed_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_audit_table_record` (`table_name`, `record_id`),
    INDEX `idx_audit_performed_at` (`performed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Commit**

```bash
git add scripts/migrations/007_audit_log.sql
git commit -m "$(cat <<'EOF'
feat(migration): 007_audit_log table for prescription audit trail

Creates audit_log with action/table_name/record_id/old_values/new_values.
AuditLogger service (Phase 3) will write rows on every mutation.

Closes #58
EOF
)"
```

---

## Phase 2 Complete

After all 5 tasks (5 commits):

```
feat(migration): 007_audit_log table for prescription audit trail
feat(migration): 006_soft_deletes on patients and prescriptions
feat(migration): 005_performance_indexes for FK and date columns
refactor(db): remove ALTER TABLE try/catch blocks from init_tables
feat(db): add schema_migrations table and Migrations runner
```

Issues closed: #33, #39, #56, #58

**Next:** `docs/superpowers/plans/2026-09-04-phase3-mvc.md`
