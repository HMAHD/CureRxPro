# CureRx Pro — Developer Wiki

Welcome to the CureRx Pro developer wiki. This is the authoritative reference for everyone working on the codebase.

## Contents

- [Development Setup](Development-Setup) — clone, install, run, configure
- [Architecture](Architecture) — layer diagram, module map, data flow
- [Testing Guide](Testing-Guide) — PHPUnit, bun/happy-dom, SQLite fixtures, CI
- [Contributing](Contributing) — branching, commit conventions, PR process, coding standards
- [API Reference](API-Reference) — endpoint catalogue, request/response shapes, token auth

---

**Quick links**

| Task | Command |
| :--- | :--- |
| Install all dependencies | `bun install && composer install` |
| Run the app | `bun run start` |
| Run all tests | `bun run test` |
| Build installer | `bun run dist` |
| Static analysis | `vendor/bin/phpstan analyse` |
