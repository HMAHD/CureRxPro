# Development Setup

## System Requirements

| Tool | Minimum | Recommended |
| :--- | :--- | :--- |
| [Bun](https://bun.sh) | 1.0 | latest |
| PHP | 8.1 | 8.2+ |
| PHP extensions | `pdo_sqlite`, `pdo_mysql` | same |
| [Composer](https://getcomposer.org) | 2.x | latest |
| MySQL | 5.7+ / MariaDB 10.4+ | MySQL 8 |
| Node.js | not required | — |

**macOS:** Install PHP and Composer via [Laravel Herd](https://herd.laravel.com) (recommended) or Homebrew.  
**Windows:** PHP and MySQL are bundled in `bin/` for the packaged app. For development, install via XAMPP or Herd for Windows.

---

## Clone and Install

```bash
git clone https://github.com/HMAHD/CureRxPro.git
cd CureRxPro

bun install          # JS dependencies (Electron, ESLint, bun test libs)
composer install     # PHP dependencies (PHPUnit, PHPStan)
```

---

## Database Configuration

1. Start a MySQL instance (XAMPP, Herd, or `bin/mysql` on Windows).
2. Open `http://localhost` in a browser or run `php db_setup.php` to launch the setup wizard.
3. The wizard writes credentials to `config/db_config.json` (gitignored — never commit this file).
4. Run migrations:

```bash
php api/run_migrations.php
```

Migrations are idempotent numbered SQL files under `scripts/migrations/`.

---

## Running the App

```bash
bun run start
```

This launches the Electron shell, which spawns:
- PHP built-in server on `localhost:8989`
- Portable MySQL on `localhost:3307` (Windows only; on macOS/Linux point to your own MySQL)

The renderer loads `index.php` via the PHP server, not as a local file, so all API calls resolve correctly.

---

## Hot Reload

There is no hot reload. Edit a file, then press `Ctrl+R` in the Electron window to refresh the renderer, or restart the app with `bun run start`.

---

## Building the Installer

```bash
bun run dist
```

Output goes to `dist_app/` (gitignored). This produces:
- `dist_app/CureRx Pro Setup <version>.exe` — NSIS installer
- `dist_app/win-unpacked/` — portable build

The `dist/` and `dist_app/` directories are safe to delete at any time; the build regenerates them.

---

## Editor Setup

The project has no enforced formatter. Follow the existing style:
- PHP: PSR-12 (4-space indent, braces on same line for control flow)
- JS: 4-space indent, single quotes, semicolons

Recommended VS Code extensions: ESLint, PHP Intelephense.
