# TRACK C — SHASHANK · Infrastructure & commerce

> Loaded via `CLAUDE.local.md` → `@docs/tracks/TRACK-C-SHASHANK.md`.
> The shared rules in root `CLAUDE.md` apply as well and are not repeated here.

---

## Who you are on this project

**Shashank — Track C, infrastructure and commerce.** You own the money tables, the queue and mail
infrastructure, and deployment preparation.

You sit **in the middle of the Phase 3 dependency chain**: you wait on Govind, and Srivathsa waits
on you. Push your commerce block promptly — someone is blocked behind it.

### Your phases, in order

| Phase | Name | Round |
|---|---|---|
| **3** | Core Domain Schema — commerce + progress slice *(current)* | 0 |
| **11** | Queues, Mail & Transactional Notifications | 1 |
| **16** | Deployment & Environments | 2 |
| **13** | Reporting & Analytics | 3 |
| **17** | Production Hardening & Observability | 4 |
| **14** | Security Hardening — *with Govind and Srivathsa* | 5 |

**Yours is the least blocked track by design.** Mail and queues need only `email_logs` — your own
table. Deployment preparation needs no domain code at all and can start whenever you like.
Reporting comes later precisely because it reads `orders` and `payments`, which you build yourself.

### Directories you own outright

`app/Jobs/**` · `app/Mail/**` · `app/Notifications/**` · `app/Services/Reporting` · `.github/**`
· `config/**` · `database/seeders`

Nobody else edits these (planning.md §21.2.2).

Phase 12 (Payments) is **Govind's**, not yours, even though you build the tables it uses. See
"What you must NOT touch".

---

# PHASE 3 — your slice

## Your migrations — create exactly these six, no others

Filenames are **fixed and pre-agreed**. Do not invent, renumber or reorder them.

| # | File | Depends on | Blocked until |
|---|---|---|---|
| 1 | `2026_08_13_100220_create_webhook_events_table.php` | — | **nothing — start here** |
| 2 | `2026_08_13_100410_create_email_logs_table.php` | — | **nothing — start here** |
| 3 | `2026_08_13_100200_create_orders_table.php` | users, **courses** | Govind pushes `100110` |
| 4 | `2026_08_13_100210_create_payments_table.php` | orders | your own #3 |
| 5 | `2026_08_13_100230_create_enrollments_table.php` | users, courses, orders, **lessons** | Govind pushes `100140` |
| 6 | `2026_08_13_100400_create_lesson_progress_table.php` | enrollments, **lessons**, users | Govind pushes `100140` |

Column specifications are in **`architecture.md` §6.4**. The constraints below are not optional
details — they are the structural guarantees the payment flow depends on (§6.5):

- **`enrollments`: `UNIQUE(user_id, course_id)`** — this single constraint is what makes enrollment
  idempotent. A replayed payment webhook cannot create a second enrollment because the database
  refuses it. Without it, Phase 12's idempotency is wishful thinking
- **`webhook_events`: `UNIQUE(event_id)`** — the idempotency key for webhook processing
- **`payments`: `UNIQUE(gateway_payment_id)`** — one gateway payment, one record
- **`orders`: `UNIQUE(gateway_order_id)`** (nullable)
- **`lesson_progress`: `UNIQUE(enrollment_id, lesson_id)`** — makes progress writes a safe upsert
  under concurrency
- Money columns are **`bigint` in paise**, never decimal, never float (ADR-007)
- `orders.buyer_email` stored **normalised lower-case** — the same email must resolve to the same
  buyer (FR-AUTH-10)
- `orders.order_number` is **composite-ready** — say so in the comment
- **Financial FKs to `users` use `RESTRICT` or `SET NULL`, never `CASCADE`.** Deleting a user must
  never delete their payment history (NFR-DATA-05)
- CHECK constraints mirroring every PHP enum (ADR-012) — copy the pattern from
  `2026_08_12_100000_create_users_table.php`
- Every table gets a tenant-owned / platform-global comment (rule S-5). All six of yours are
  tenant-owned

## Your other Phase 3 deliverables

- **Enums:** `OrderStatus`, `PaymentStatus`, `EnrollmentStatus`, `EnrollmentSource`,
  `ProgressStatus`, `CompletionSource`
- **Models:** `Order`, `Payment`, `WebhookEvent`, `Enrollment`, `LessonProgress`, `EmailLog`
- **Scope:** `Enrollment::active()`
- **Policies:** `OrderPolicy`, `PaymentPolicy`, `EnrollmentPolicy` — registered, denying by default
  - `OrderPolicy` and `PaymentPolicy` must **deny instructors outright** (FR-INS-10). An instructor
    never sees a financial figure anywhere in this system
- **Factories** for all six models

---

## DEPENDENCY WAITS — read this before you start

### 🔴 You are BLOCKED on Govind for four of your six migrations

| You cannot migrate | Until Govind pushes |
|---|---|
| `orders` (`100200`) | `2026_08_13_100110_create_courses_table.php` |
| `payments` (`100210`) | *(via your own orders)* |
| `enrollments` (`100230`) | `2026_08_13_100140_create_lessons_table.php` |
| `lesson_progress` (`100400`) | `2026_08_13_100140_create_lessons_table.php` |

### How to check whether Govind's block has landed

```bash
git fetch origin
git ls-tree origin/main --name-only database/migrations/ | grep -E "100110|100140"
```

Two filenames returned → you are unblocked. Then:

```bash
git rebase origin/main
php artisan migrate:fresh --seed
```

### What to do WHILE blocked — start here, today

1. **`webhook_events`** (`100220`) — zero dependencies. Write the migration, model, enum, factory
   and policy now.
2. **`email_logs`** (`100410`) — zero dependencies. Same.
3. **Write** the other four migrations. You can author the files; you simply cannot *run* them
   until Govind's tables exist. Writing them now means you migrate the moment he pushes.
4. **Write the enums.** All six are pure PHP with no database dependency.
5. **Read** `architecture.md` §11 (payment architecture) and §13 (queue architecture). You will
   build Phase 11 next and it is where your track spends most of its time.

### 🚫 What you must NOT do while blocked

- **Do not create `courses` or `lessons` yourself.** Not even a stub, not even "temporarily".
  Two people creating the same migration is the most expensive mistake available here.
- **Do not comment out the foreign keys** to get your migrations running. A commented-out FK has a
  way of never being uncommented, and the constraint is the guarantee.
- **Do not renumber** your migrations to run before his.

### 🟡 Who is blocked on YOU

**Srivathsa cannot migrate `assessment_attempts` (`100330`) until your `enrollments` (`100230`)
is on `main`.** You are in the middle of the chain. The moment Govind unblocks you, push
`enrollments` promptly and tell Srivathsa.

---

## Your daily loop

```bash
git fetch origin && git rebase origin/main
git checkout -b phase/03-commerce-schema

# ... work ...

composer check          # lint + analyse + test. All three green, always.
git push -u origin phase/03-commerce-schema
# open PR, get review, merge
```

Merge daily. **Announce when `enrollments` lands** — Srivathsa is waiting on it.

---

## What you must NOT touch

| Belongs to | Files |
|---|---|
| **Govind (A)** | `categories`, `courses`, `course_instructor`, `modules`, `lessons`, `media_files`; **`EnrollmentAccessService`**; **`GrantEnrollment`**; `bootstrap/app.php`; migration ordering |
| **Srivathsa (B)** | `assessments`, `questions`, `question_options`, `assessment_attempts`, `attempt_answers`; `resources/views/components/` |

### Especially: the enrollment service and action

You build the `enrollments` **table**. Govind owns the **service and action** that give it meaning
— `EnrollmentAccessService` and `GrantEnrollment`. That split is deliberate.

This also means **Phase 12 (Payments) is Govind's, not yours**, even though you built `orders`,
`payments` and `webhook_events`. Phase 12's whole design is "call the already-tested
`GrantEnrollment`, unchanged", and that is far harder to hold to for someone meeting the action
for the first time. You hand him working tables; he wires the money to the access.

You **do** own `composer.json` and `config/lms.php` — if another track needs a dependency or a
config key, it comes through you, with a recorded Rule 6 justification for any new package.

---

## Phase 3 Definition of Done

The shared checklist is in `phases.md` Phase 3. The items that are specifically yours:

- [ ] `UNIQUE(user_id, course_id)` on enrollments — **proven by a test that expects the insert to
      throw**, not merely by reading the migration
- [ ] `UNIQUE(event_id)`, `UNIQUE(gateway_payment_id)`, `UNIQUE(enrollment_id, lesson_id)` — same
- [ ] Deleting a user does **not** delete their orders (cascade behaviour test)
- [ ] CHECK constraints reject illegal enum values at the database level
- [ ] `OrderPolicy` and `PaymentPolicy` deny instructors
- [ ] Every migration comment classifies the table for future tenancy

---

## After Phase 3 — you are the least blocked track

Once Gate G1 clears, **Phase 11 (Queues & Mail) needs only `email_logs`** — which is yours. You can
run nearly the whole phase without waiting on anyone.

You may also start **Phase 16 deployment preparation early** — hosting choice, Redis, S3 bucket
policy, the deploy sequence. It has no code dependency on the domain at all.

Two things to know now:

- The mail layer must be **transport-agnostic**. Development uses Mailpit/`log` throughout; the
  production provider is chosen in Phase 16 (PD-07). Switching must be configuration only
- Every email resolves organisation identity through `BrandingService` — never hardcoded. That is
  the seam that makes per-organisation branding a config change in V2 (rule S-1)
