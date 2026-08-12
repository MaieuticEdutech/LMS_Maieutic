# TRACK A — GOVIND · Domain trunk

> Loaded via `CLAUDE.local.md` → `@docs/tracks/TRACK-A-GOVIND.md`.
> The shared rules in root `CLAUDE.md` apply as well and are not repeated here.

---

## Who you are on this project

**Govind — Track A, the domain trunk.** You own the critical path and the two components the
entire system's security guarantees rest on.

**You go first.** Nothing else in Phase 3 can be migrated until your catalogue block is on `main`.
Srivathsa and Shashank are both waiting on you on day one. Push early, push in pieces.

### Your phases, in order

| Phase | Name |
|---|---|
| **3** | Core Domain Schema — catalogue + media slice *(current)* |
| 5 | Course Builder & Content Management |
| **6** | **Enrollment Core & Protected Delivery — single-owner** |
| 7 | Student Learning Experience |
| 9 | Progress Tracking |
| **12** | **Payments & Automated Enrollment — single-owner, later** |

---

## YOU ARE THE SINGLE OWNER OF THESE — nobody else may touch them

| Component | Phase |
|---|---|
| `app/Services/Enrollment/EnrollmentAccessService.php` | 3 (skeleton) → 6 (complete) |
| `app/Actions/Enrollment/GrantEnrollment.php` | 3 (skeleton) → 6 (complete) |
| The webhook → enrollment path | 12 |

**Why this matters and is not bureaucracy:** ADR-006 guarantees there is exactly **one** code path
that creates an enrollment and exactly **one** definition of "has access". That guarantee is an
ownership decision, not something code review reliably catches. If two people work near it, a
second path appears — and a second path to course access is the exact failure the customer built
this system to prevent.

Srivathsa and Shashank consume both as **read-only interfaces**. If either asks for a change, you
make it. They do not.

Note that `enrollments` the **table** belongs to Shashank (Track C), but the enrollment
**service and action** belong to you. That split is deliberate: he builds the storage, you own
the meaning.

---

# PHASE 3 — your slice

## Your migrations — create exactly these six, no others

Filenames are **fixed and pre-agreed**. Do not invent, renumber or reorder them.

| # | File | Depends on |
|---|---|---|
| 1 | `2026_08_13_100100_create_categories_table.php` | — |
| 2 | `2026_08_13_100110_create_courses_table.php` | categories, users |
| 3 | `2026_08_13_100120_create_course_instructor_table.php` | courses, users |
| 4 | `2026_08_13_100130_create_modules_table.php` | courses |
| 5 | `2026_08_13_100140_create_lessons_table.php` | modules |
| 6 | `2026_08_13_100150_create_media_files_table.php` | users *(polymorphic attachable — no FK)* |

Column specifications are in **`architecture.md` §6.4**. Follow them exactly, including:

- FKs with explicit `ON DELETE` behaviour — `CASCADE` down the content hierarchy
  (course → modules → lessons), `SET NULL` for `category_id`
- CHECK constraints mirroring every PHP enum (ADR-012) — copy the pattern from
  `2026_08_12_100000_create_users_table.php`
- `courses.price_amount` is `bigint` **with `CHECK > 0`** — all V1 courses are paid (ADR-014)
- **No `is_free` column** on courses. **No `is_preview` column** on lessons. Both were removed by
  business decision (ADR-014). Do not add them back "for later"
- Every table gets a comment classifying it **tenant-owned** or **platform-global** (rule S-5).
  All six of yours are tenant-owned
- `courses.slug` and `categories.slug` are **composite-ready** — say so in the comment. In V2 they
  become `(organisation_id, slug)`

## Your other Phase 3 deliverables

- **Enums:** `CourseStatus`, `CourseLevel`, `LessonType`, `MediaPurpose`
- **Models:** `Category`, `Course`, `Module`, `Lesson`, `MediaFile`
- **Scopes:** `Course::published()`, `Course::assignedTo()`, `Lesson::published()`
  - `Course::assignedTo()` is the **entire basis of instructor authorisation** (§8.4). Every
    instructor query in Phase 10 begins here. Get it right.
- **Policies:** `CoursePolicy`, `ModulePolicy`, `LessonPolicy`, `MediaFilePolicy` — registered and
  **denying by default**
- **Factories** for all five models
- **`EnrollmentAccessService::grantsAccess()`** — your single-owner component. Skeleton in Phase 3,
  completed in Phase 6
- **`GrantEnrollment`** skeleton — idempotent, transactional, audited. Fully exercised in Phase 6

---

## Dependency waits — WHO YOU WAIT FOR

**Nobody. You are the head of the chain.** Every migration you write depends only on `users`,
which is already on `main` from Phase 2.

This means **you are the bottleneck on day one.** Two people are idle until you push.

### Your obligation to the others

| Push this | Unblocks |
|---|---|
| `2026_08_13_100110_create_courses_table.php` | Shashank's `orders` |
| `2026_08_13_100140_create_lessons_table.php` | Shashank's `enrollments` and `lesson_progress` |

**Push the catalogue block (`100100`–`100150`) as your FIRST pull request, on day one, before you
write a single model, policy or factory.** Migrations alone, merged and green. Then go back and do
the models.

This inverts the natural order — you would normally build a migration and its model together — and
it is worth it. Six migration files unblock two developers for two days of work. Do the tidy thing
second.

**Tell Shashank the moment it is merged.**

---

## Your daily loop

```bash
git fetch origin && git rebase origin/main
git checkout -b phase/03-catalogue-schema

# ... work ...

composer check          # lint + analyse + test. All three green, always.
git push -u origin phase/03-catalogue-schema
# open PR, get review, merge
```

Merge daily. Tell the team when a dependency of theirs lands.

---

## What you must NOT touch

| Belongs to | Files |
|---|---|
| **Srivathsa (B)** | `assessments`, `questions`, `question_options`, `assessment_attempts`, `attempt_answers` migrations/models/policies; `resources/views/components/` |
| **Shashank (C)** | `orders`, `payments`, `webhook_events`, `enrollments`, `lesson_progress`, `email_logs` migrations/models/policies; `composer.json`; `config/lms.php` |

You own `database/migrations/` **ordering** and `bootstrap/app.php`, so if either of them needs a
change there, they come to you.

If you find yourself needing one of their files: **stop.** Do not create it. Ask.

---

## Phase 3 Definition of Done — yours to drive

This is the PR review checklist (`phases.md` Phase 3):

- [ ] All domain tables migrated with constraints and indexes
- [ ] Every model has a factory and a **registered** policy
- [ ] `migrate:fresh --seed` succeeds and yields realistic data
- [ ] Database-level invariants (`architecture.md` §6.5) each covered by a test
- [ ] Every migration comment classifies the table for future tenancy
- [ ] `EnrollmentAccessService` exists and is the **only** definition of access
- [ ] Universal DoD satisfied — Pint clean, Larastan L8 zero errors, whole Pest suite green

**Gate G1** clears only when all three tracks' migrations are merged and `migrate:fresh --seed` is
green on `main`. Nobody starts Phase 4/5/11 before that.

---

## Looking ahead — Phase 6 is the one that matters

When you reach it, re-read `architecture.md` §12 and ADR-006 before writing anything. Phase 6 is
the highest-risk phase in the whole project: everything downstream trusts the access gate, and
Phase 12 is built by calling your `GrantEnrollment` **unchanged**.

Build it as though the person calling it will not read it. Because they will not.
