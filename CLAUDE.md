# CLAUDE.md — LMS project (shared)

This file is loaded automatically for **every** developer. It holds the rules that apply to
everyone. Your personal, track-specific brief is a separate file — see "Set up your track" below.

---

## Set up your track (do this once, per machine)

Create a **git-ignored** file `CLAUDE.local.md` in the repo root containing exactly one line —
the import for your own track brief:

| Developer | Track | Put this line in `CLAUDE.local.md` |
|---|---|---|
| **Govind** | A — Domain trunk | `@docs/tracks/TRACK-A-GOVIND.md` |
| **Srivathsa** | B — Surfaces | `@docs/tracks/TRACK-B-SRIVATHSA.md` |
| **Shashank** | C — Infrastructure & commerce | `@docs/tracks/TRACK-C-SHASHANK.md` |

```bash
# Govind, for example:
echo '@docs/tracks/TRACK-A-GOVIND.md' > CLAUDE.local.md
```

`CLAUDE.local.md` is git-ignored, so it never conflicts. The track briefs themselves **are**
committed, so everyone can see what the other tracks are doing — which is the point.

---

## What this project is

A Learning Management System for one organisation. Courses are sold online; **course access is
granted only against a payment the backend has independently verified.**

Laravel 13.25 · PHP 8.5 · PostgreSQL 17 · Livewire 4 · Fortify · Tailwind 4 · Pest · Larastan L8

**Current state: Phase 2 complete. Phase 3 is next.** 11 tables exist (7 framework + `users`,
`instructor_profiles`, `settings`, `audit_logs`). No course, enrollment, payment or assessment
table exists yet.

---

## Read these before writing code

Development Rule 2 requires it, and it is not ceremony — these four documents are the
specification, and the code is downstream of them.

| Document | What it is |
|---|---|
| `requirements.md` | What is built. Traceable IDs (`FR-*`, `NFR-*`, `AC-*`) |
| `architecture.md` | How it is built. Schema, layering, ADRs |
| `phases.md` | In what order. Your phase's Definition of Done lives here |
| `planning.md` | Development control. Rules, conventions, decisions. **§21 = the track model** |

If code and documents disagree, that is a defect. Decide which is wrong and fix it — do not guess.

---

## The five rules nobody may break

1. **A browser saying "payment succeeded" grants nothing.** Enrollment comes only from a
   signature-verified webhook or an audited admin grant (Rules 21–22, ADR-006).
2. **Backend policy is the only authority.** Hiding a UI element is never security (Rule 20).
   Every fetch-by-ID is followed by a policy check.
3. **`GrantEnrollment` and `EnrollmentAccessService` are single-owner.** They belong to Track A
   (Govind). Nobody else creates, edits or works around them — consume them as read-only
   interfaces. If you need a change, ask. Do not write your own.
4. **Never hardcode secrets. Never commit `.env`.** A secret that reaches git history is leaked
   and must be rotated.
5. **Never mark a phase complete without its Definition of Done** (Rule 25).

---

## Conventions you will need constantly

- **Roles:** always `$user->hasRole(UserRole::X)`. Never `$user->role === 'x'`. This is the only
  permitted pattern (rule S-7) and it is what makes future many-to-many roles cheap.
- **Access:** always `EnrollmentAccessService::grantsAccess()`. Never an ad-hoc enrollment query
  (rule S-8). One definition of "has access" in the whole system.
- **Money:** always `App\Support\Money`, integer paise. Never a float, never a decimal cast.
- **Business logic:** in `app/Actions/{Domain}` and `app/Services/{Domain}`. Never in controllers
  or Livewire components (Rule 16).
- **Enums:** every status and type is a PHP enum with a matching database CHECK constraint.
- **`$fillable` always.** `$guarded = []` is prohibited. `role`, `status`, `price_amount` and
  ownership fields are never fillable (NFR-SEC-07).
- **Organisation identity** comes from `SettingsRepository` / `BrandingService`, never hardcoded.
  This is the multi-tenancy seam (rule S-1) — it is why V2 is a migration and not a rewrite.
- **Migrations** carry a comment classifying the table **tenant-owned** or **platform-global**.

---

## Before every push — all three gates must be green

```bash
composer lint       # Pint
composer analyse    # Larastan level 8, zero errors
composer test       # Pest, whole suite
```

Or `composer check` for all three. **A red gate is never pushed.** The level is never lowered and
no baseline is ever added to make a failing build pass (Rule 9).

---

## Git workflow (parallel team — planning.md §21.6)

| # | Rule |
|---|---|
| P-1 | Branch per **task**, not per track: `phase/NN-short-name`. Long-lived branches are merge debt |
| P-2 | Merge to `main` **daily**, even mid-phase. A branch older than ~2 days is a warning sign |
| P-3 | Every merge goes through a pull request. No direct pushes to `main` |
| P-4 | CI green before merge |
| P-5 | The phase's **Definition of Done is the PR review checklist** |
| P-6 | A PR touching another track's single-owner component needs that owner's review |
| P-7 | Rebase on `main` before opening a PR; resolve your own conflicts |
| P-8 | `main` stays deployable at all times |

---

## Shared files — check the owner before editing

| File | Owner |
|---|---|
| `database/migrations/` | Track A (Govind) — filenames agreed in advance, never renumber a merged migration |
| `bootstrap/app.php` | Track A (Govind) |
| `composer.json` / `package.json` | Track C (Shashank) — a new dependency needs a recorded Rule 6 justification |
| `config/lms.php` | Track C (Shashank) — add keys, never repurpose |
| `resources/views/components/` | Track B (Srivathsa) — extend, don't fork. A second button component is a defect |
| `requirements.md`, `architecture.md` | Team decision — agree before editing |

If a file you need is not yours: **stop and ask the owner.** Do not edit it "just this once".

---

## When you are blocked by another track

This will happen — it is designed in, not a failure. The protocol:

1. **Verify the dependency really has not landed:**
   ```bash
   git fetch origin
   git ls-tree origin/main --name-only database/migrations/
   ```
2. **Do not create the missing file yourself.** Not even a stub. Two people creating the same
   migration is the single most expensive mistake available on this project.
3. **Do not work around it** with a temporary table, a fake model or a commented-out FK.
4. **Switch to unblocked work** — your track brief lists what that is.
5. **Tell your teammate you are waiting.** Then re-check with `git fetch origin`.

---

## Environment

PHP 8.5 with `pdo_pgsql`, `pgsql`, `mbstring`, `fileinfo`, `openssl`, `curl`, `zip`, `gd`, `intl`,
`exif`, `sodium` · Composer 2 · PostgreSQL 16+ · Node 20+.

Each developer needs their own `lms_dev` and `lms_test` databases. `README.md` has full setup;
`planning.md` §20.1 records a no-admin portable route if you want it.

Tests run against **real PostgreSQL** (`lms_test`), never SQLite — the schema depends on JSONB,
partial unique indexes and CHECK constraints that SQLite does not implement.

---

## Do not build ahead

Your track brief names your phase. Anything outside it — including a "quick" helper another phase
will need — is out of scope (Rule 5). If you genuinely need something from a future phase, that is
a conversation, not a commit.
