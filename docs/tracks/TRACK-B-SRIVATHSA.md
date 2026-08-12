# TRACK B — SRIVATHSA · Surfaces

> Loaded via `CLAUDE.local.md` → `@docs/tracks/TRACK-B-SRIVATHSA.md`.
> The shared rules in root `CLAUDE.md` apply as well and are not repeated here.

---

## Who you are on this project

**Srivathsa — Track B, the surfaces.** You own the assessment engine and every administrative and
instructor-facing screen. Yours is the heaviest Livewire and Blade load on the project.

You sit **at the end of the Phase 3 dependency chain** — you wait on Shashank, who waits on Govind.
That sounds bad but is not: four of your five migrations have no cross-track dependency at all, so
you can start immediately and only the last one waits.

### Your phases, in order

| Phase | Name | Round |
|---|---|---|
| **3** | Core Domain Schema — assessment slice *(current)* | 0 |
| **4** | Admin Shell & Administration Area | 1 |
| **8** | Assessment Engine — authoring, then the attempt runner | 2–3 |
| **10** | Instructor Module | 4 |
| **14** | Security Hardening — *with Govind and Shashank* | 5 |
| **15** | UI/UX Polish & Accessibility | 6 |

### Directories you own outright

`app/Livewire/**` · `resources/views/**` · `app/Actions/Assessment` · `app/Services/Assessment`

Nobody else edits these. That is what lets you push freely without collision — your files and
Govind's barely overlap (planning.md §21.2.2).

### 🔴 The one thing the whole team waits on you for

**Phase 4's admin shell blocks Govind's Course Builder.** The Course Builder is a screen *inside*
your admin area, so until the shell — layout, navigation, breadcrumbs, the reusable admin table
component — is merged, he cannot build the Phase 5 UI.

**Build the shell first and merge it fast, then continue with student and instructor management.**
He is working around it by building Phase 5's backend first, but that only buys a few days.

---

# PHASE 3 — your slice

## Your migrations — create exactly these five, no others

Filenames are **fixed and pre-agreed**. Do not invent, renumber or reorder them.

| # | File | Depends on | Blocked until |
|---|---|---|---|
| 1 | `2026_08_13_100300_create_assessments_table.php` | users *(polymorphic assessable — **no FK**)* | **nothing — start here** |
| 2 | `2026_08_13_100310_create_questions_table.php` | assessments | your own #1 |
| 3 | `2026_08_13_100320_create_question_options_table.php` | questions | your own #2 |
| 4 | `2026_08_13_100330_create_assessment_attempts_table.php` | assessments, users, **enrollments** | Shashank pushes `100230` |
| 5 | `2026_08_13_100340_create_attempt_answers_table.php` | assessment_attempts, questions | your own #4 |

**Four of your five have no cross-track dependency.** `assessments` attaches polymorphically to a
Lesson, Module or Course, which means **no foreign key** — so you are not waiting on Govind at all.
Only `assessment_attempts` waits, because it references `enrollments`.

Column specifications are in **`architecture.md` §6.4**. Two things carry unusual weight:

- **`assessment_attempts` needs a PARTIAL UNIQUE INDEX:**
  ```sql
  CREATE UNIQUE INDEX assessment_attempts_one_in_progress
      ON assessment_attempts (assessment_id, user_id)
      WHERE status = 'in_progress';
  ```
  This single constraint is what enforces "a student cannot have two simultaneous attempts"
  (FR-ASMT-16, AC-26) **at the database level**. Application checks race; this does not. It is also
  why the test suite runs on real PostgreSQL — SQLite has no partial indexes, so a green SQLite
  suite would prove nothing here.

- **`question_options.is_correct`** is the answer key. From Phase 8 onward it must never be
  serialised to a student before submission (NFR-SEC-21, AC-23). Build the column now; remember the
  rule when you build the presenter.

Also required on every table:

- CHECK constraints mirroring every PHP enum (ADR-012) — copy the pattern from
  `2026_08_12_100000_create_users_table.php`
- `ON DELETE CASCADE` from assessment → questions → options, and attempt → answers
- `UNIQUE(attempt_id, question_id)` on `attempt_answers`
- `UNIQUE(assessment_id, user_id, attempt_number)` on `assessment_attempts`
- A tenant-owned / platform-global comment (rule S-5). All five of yours are tenant-owned

## Your other Phase 3 deliverables

- **Enums:** `AssessmentType` (quiz|test), `QuestionType`, `AnswerRevealPolicy`, `AttemptStatus`
- **Models:** `Assessment`, `Question`, `QuestionOption`, `AssessmentAttempt`, `AttemptAnswer`
- **Scope:** `Assessment::published()`
- **Policies:** `AssessmentPolicy`, `AttemptPolicy` — registered, denying by default
- **Factories** for all five models

### One design note worth internalising now

There is **no `quizzes` table and no `tests` table.** A quiz and a test are the same structure with
different attachment points — one `assessments` table with a `type` discriminator (ADR-002). A quiz
attaches to a Lesson or Module; a test attaches to a Course. One engine, one policy set, half the
code, half the bugs. When you build Phase 8, resist every instinct to split them.

---

## DEPENDENCY WAITS — read this before you start

### 🟢 You are NOT blocked today

Start immediately on `assessments` → `questions` → `question_options`, plus all four enums and the
`Assessment::published()` scope. That is most of your Phase 3 work and none of it waits on anyone.

### 🔴 One migration is blocked — on Shashank, not Govind

| You cannot migrate | Until Shashank pushes |
|---|---|
| `assessment_attempts` (`100330`) | `2026_08_13_100230_create_enrollments_table.php` |
| `attempt_answers` (`100340`) | *(via your own attempts table)* |

Shashank is himself waiting on Govind's `lessons` table, so this is a **two-hop wait**. Expect it
to clear on day two, not day one.

### How to check whether you are unblocked

```bash
git fetch origin
git ls-tree origin/main --name-only database/migrations/ | grep 100230
```

Filename returned → unblocked. Then:

```bash
git rebase origin/main
php artisan migrate:fresh --seed
```

### What to do WHILE blocked

1. **Everything in the 🟢 list above** — that is roughly 70% of your Phase 3 slice.
2. **Write** `assessment_attempts` and `attempt_answers` anyway. You can author the files; you
   simply cannot *run* them until `enrollments` exists. Then you migrate the moment it lands.
3. **Read** `architecture.md` §10 (assessment architecture) — the attempt lifecycle, the grading
   flow, and the `QuestionTypeRegistry`. Phase 8 is the largest phase you own.
4. **Read** `architecture.md` §8.4 — the instructor scope. Every instructor query in Phase 10 must
   begin from `Course::assignedTo($user)`, and that shapes how you build Phase 10.

### 🚫 What you must NOT do while blocked

- **Do not create the `enrollments` table yourself.** Not even a stub. It is Shashank's, and two
  people creating the same migration is the most expensive mistake available here.
- **Do not drop the `enrollment_id` foreign key** to get your migration running. That FK is what
  ties an attempt to a specific course grant.
- **Do not renumber** your migrations to run before his.

### 🟡 Who is blocked on you

**Nobody, in Phase 3.** You are last in the chain. That is a good position — use the head start on
`assessments` and be ready to migrate the instant Shashank pushes.

---

## Your daily loop

```bash
git fetch origin && git rebase origin/main
git checkout -b phase/03-assessment-schema

# ... work ...

composer check          # lint + analyse + test. All three green, always.
git push -u origin phase/03-assessment-schema
# open PR, get review, merge
```

Merge daily, even mid-phase.

---

## What you must NOT touch

| Belongs to | Files |
|---|---|
| **Govind (A)** | `categories`, `courses`, `course_instructor`, `modules`, `lessons`, `media_files`; **`EnrollmentAccessService`**; **`GrantEnrollment`**; `bootstrap/app.php`; migration ordering |
| **Shashank (C)** | `orders`, `payments`, `webhook_events`, `enrollments`, `lesson_progress`, `email_logs`; `composer.json`; `config/lms.php` |

### The access gate is not yours to reimplement

When Phase 8 needs "is this student allowed to start this attempt?", the answer is
`EnrollmentAccessService::grantsAccess()` — Govind's single-owner component. Never write your own
enrollment lookup, however small (rule S-8). There is exactly one definition of "has access" in
this system, and keeping it that way is what makes it testable.

---

## You DO own the shared component library

`resources/views/components/` is yours. Twelve components exist already (`x-button`, `x-card`,
`x-input`, `x-select`, `x-textarea`, `x-checkbox`, `x-table`, `x-modal`, `x-alert`, `x-badge`,
`x-empty-state`, `x-pagination`).

**Extend them; never fork them.** A second button component is a defect, not a preference — Phase
15's polish pass exists to remove exactly that kind of drift, and every one you avoid creating now
is one nobody has to hunt down later. If a component needs a new variant, add the variant.

---

## Phase 3 Definition of Done

The shared checklist is in `phases.md` Phase 3. The items that are specifically yours:

- [ ] **The partial unique index exists and is proven by test** — two `in_progress` attempts for
      the same student and assessment must throw at the database level
- [ ] `UNIQUE(attempt_id, question_id)` and `UNIQUE(assessment_id, user_id, attempt_number)` — same
- [ ] CHECK constraints reject illegal enum values at the database level
- [ ] Deleting an assessment cascades to questions and options
- [ ] Every model has a factory and a registered policy
- [ ] Every migration comment classifies the table for future tenancy

---

## After Phase 3 — you are the first to start real feature work

Once Gate G1 clears, **Phase 4 (Admin Shell) needs only Phase 3**, so you start immediately while
Govind is still on Phase 5.

Note the ordering subtlety: Govind's Phase 5 Course Builder **lives inside your admin shell**. So
build the shell — layout, navigation, the reusable admin table pattern, breadcrumbs, flash region —
**early and merge it fast**, then continue with user management. He needs the shell before he can
put a Course Builder in it.

That makes you the day-one bottleneck at Gate G1, exactly as Govind was at the start of Phase 3.
