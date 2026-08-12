# LMS — Learning Management System

A single-organisation Learning Management System: sell course access online, deliver protected
learning content, and measure student progress.

**Current status: Phase 1 — Project Foundation, complete.**
No application features exist yet. See [planning.md](planning.md) for the authoritative status.

---

## Read these before writing any code

This project is governed by four planning documents. Development Rule 2 requires reading all
four before starting a phase.

| Document | Purpose |
|---|---|
| [requirements.md](requirements.md) | What is built — traceable requirement IDs, acceptance criteria |
| [architecture.md](architecture.md) | How it is built — layering, schema, ADRs, diagrams |
| [phases.md](phases.md) | In what order — 19 phases, each with a Definition of Done |
| [planning.md](planning.md) | Development control — rules, standards, decisions, risks, current phase |

**Three rules that override convenience:**

1. A browser saying "payment succeeded" grants nothing. Enrollment comes only from a
   signature-verified webhook or an audited admin grant (Rules 21–22, ADR-006).
2. Backend policy is the only authority. Hiding a UI element is never security (Rule 20).
3. One phase at a time. No phase starts before the previous one meets its Definition of Done
   (Rules 1, 25).

---

## Stack

| Layer | Technology | Installed version |
|---|---|---|
| Language | PHP | 8.5.9 (NTS, x64) |
| Framework | Laravel | 13.25.0 (pinned `^13.0`) |
| Database | PostgreSQL | 17.10 |
| Interactivity | Livewire | 4.4.0 |
| Authentication | Laravel Fortify (headless, LMS-owned views) | 1.38.0 |
| Styling | Tailwind CSS 4 + Vite 8 | — |
| Testing | Pest | 5.1.0 |
| Static analysis | Larastan (level 8) | 3.10.0 |
| Code style | Laravel Pint | 1.30.5 |

---

## Requirements

- PHP **8.5** with extensions: `curl`, `exif`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`,
  `pdo_pgsql`, `pgsql`, `sodium`, `zip`
- Composer 2.x
- PostgreSQL 16+
- Node 20+ and npm

> PHP 8.5 is required, not merely supported. Pest 5 and `pest-plugin-laravel` both floor at
> `^8.4`, and the project pins `^8.5` in `composer.json` so an unsupported runtime fails at
> install time with a clear message rather than mid-suite with a confusing one.

---

## Local setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Configure environment
cp .env.example .env
php artisan key:generate

# 3. Create the databases (both are required — tests use lms_test)
createdb lms_dev
createdb lms_test

# 4. Point .env at your PostgreSQL instance
#    DB_HOST / DB_PORT / DB_USERNAME / DB_PASSWORD

# 5. Migrate
php artisan migrate

# 6. Run
npm run dev          # Vite dev server with HMR
php artisan serve    # http://localhost:8000
```

Visit `/up` to confirm the database, cache and content storage are all reachable.

---

## Commands

| Command | Purpose |
|---|---|
| `composer test` | Run the Pest suite |
| `composer lint` | Check formatting (Pint, no changes written) |
| `composer lint:fix` | Apply formatting |
| `composer analyse` | Static analysis (Larastan, level 8) |
| `composer check` | All three gates — run before every commit |
| `npm run dev` | Vite dev server |
| `npm run build` | Production assets |

---

## Environment variables

Every variable is documented in [.env.example](.env.example). Rules:

- `.env` is **never** committed. A secret that reaches git history is considered leaked and
  must be **rotated** — deleting the commit is not sufficient.
- `env()` is called only inside `config/`. Application code reads `config()`.
- Organisation-level **settings** — name, logo, support email, thresholds — belong in the
  `settings` database table (from Phase 2), **not** in `.env`. Only infrastructure credentials
  and environment endpoints belong there. This separation is what makes future
  multi-organisation support a configuration change rather than a rewrite.

---

## Testing

Tests run against a **real PostgreSQL database** (`lms_test`), never SQLite. From Phase 3 the
schema depends on JSONB, partial unique indexes and CHECK constraints, none of which SQLite
implements — a green suite on SQLite would be worse than no suite at all.

- `tests/Feature` — full application, real database. The primary layer: authorisation,
  enrollment and payment behaviour cannot be proven with mocks.
- `tests/Unit` — pure logic, no container, no database.

---

## Project structure

```
app/
├── Actions/       one class = one write use-case (business logic lives here, not in controllers)
├── Services/      reusable capabilities (storage paths, grading, payments, progress)
├── Policies/      record-level authorisation — the authority for access decisions
├── Enums/         every status and type, backed by DB CHECK constraints
├── Support/       framework-agnostic value objects (Money)
├── Livewire/      interactive components
└── Http/          controllers, middleware, form requests

routes/
├── web.php         public — course metadata only, never protected content
├── admin.php       super_admin
├── instructor.php  instructor, assigned courses only
├── student.php     student, enrolled content
├── media.php       protected media delivery — the only path to learning content
└── webhooks.php    gateway callbacks — CSRF-exempt, signature-verified

storage/app/content/   protected learning material — private, never web-served, git-ignored
```

---

## Licence

Proprietary. All rights reserved.
