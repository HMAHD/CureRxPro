# Contributing

## Getting Started

1. Fork the repository and clone your fork.
2. Follow the [Development Setup](Development-Setup) guide.
3. Create a feature branch from `main` (see branching conventions below).
4. Make your changes, write or update tests, and open a pull request.

---

## Branching Conventions

| Branch prefix | Use for |
| :--- | :--- |
| `feat/` | New features |
| `fix/` | Bug fixes |
| `refactor/` | Code restructuring without behaviour change |
| `test/` | Adding or improving tests only |
| `ci/` | CI/CD changes |
| `docs/` | Documentation only |
| `chore/` | Dependency updates, tooling, config |

Branch off `main`. Long-lived topic branches should be rebased onto `main` before opening a PR.

---

## Commit Conventions

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <short summary>
```

Types: `feat`, `fix`, `refactor`, `test`, `ci`, `docs`, `chore`

Scope examples: `queue`, `prescriptions`, `modal`, `validator`, `ci`

**Good:**
```
fix(modal): switch from style.display to classList.active mechanism
test(integration): add PatientRepositoryTest with SQLite fixtures
```

**Bad:**
```
fixed stuff
WIP
```

Keep the subject line under 72 characters. Add a body if the why is non-obvious.

---

## Pull Request Process

1. **Tests must pass.** Run `bun run test` locally before opening the PR.
2. **PHPStan must pass.** Run `vendor/bin/phpstan analyse` — level 5, no errors.
3. **One concern per PR.** Separate refactors from feature additions.
4. **Fill in the PR template.** Describe what changed and how you tested it.
5. **Link issues.** Use `Closes #<n>` in the PR description to auto-close the issue on merge.

PRs go through at least one review before merge. The CI pipeline (PHPStan + ESLint + PHPUnit + bun test) must be green.

---

## Coding Standards

### PHP

- Follow PSR-12.
- All new code goes under `src/` with the `CureRx\` namespace (PSR-4 autoloaded).
- API endpoint files in `api/` are thin dispatchers only — no business logic there.
- Every SQL query must use `PDO::prepare()` with bound parameters. Never interpolate user input into SQL.
- Use `AuditLogger::log()` for any data mutation that should be auditable.
- Validate all incoming request fields through `Validator` before using them.

### JavaScript

- Native ES modules only (`import`/`export`). No CommonJS, no bundler.
- No jQuery. No UI framework. Vanilla JS.
- Cross-module communication: dispatch a `crx:`-prefixed `CustomEvent` on `document`. Do not import one module from another where a circular dependency could form.
- Modal state: use `openModal(id)` / `closeModal(id)` from `components/modal.js`. Never touch `style.display` on a `.modal-overlay` element directly.
- Use `api.get()` / `api.post()` from `modules/api.js` — never raw `fetch()` — to ensure the auth token header is always sent.

### Tests

- Every new PHP class in `src/` should have at least a unit test.
- Use SQLite integration tests for repositories and services that do not use MySQL-specific syntax.
- Use mock PDO for anything that requires `FOR UPDATE`, `CURDATE()`, or other MySQL-only features.
- No Mockery — PHPUnit mock objects only.
- JS tests use `bun:test` imports. No Jest, no Vitest.

---

## Reporting Issues

Use the GitHub issue templates:
- **Bug report** — include steps to reproduce, expected vs actual behaviour, and the OS/version.
- **Feature request** — describe the clinical workflow problem you are solving, not just the technical implementation.

Security vulnerabilities: do not open a public issue. Contact the maintainer directly at the email listed on the GitHub profile.

---

## Project Structure Conventions

- New feature modules go in `assets/js/modules/`. Register them in `main.js`.
- New UI components (reusable, stateless) go in `assets/js/components/`.
- New PHP services go in `src/Services/`, repositories in `src/Repositories/`.
- New API endpoints: add a dispatcher in `api/`, add the controller/service logic to `src/`.
- New database tables: add a migration file to `scripts/migrations/` with the next sequence number.
