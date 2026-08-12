# PROGRESS.md — Track B (Srivathsa)

Checkpoint log for Phase 3, Track B (`docs/tracks/TRACK-B-SRIVATHSA.md`). One entry per checkpoint:
what landed, gate results, and any deviation from the plan worth a future reader knowing about.

---

## Known gaps carried forward — check this before assuming Track B is done

- **`AssessmentPolicy` has no instructor branch.** Since Checkpoint 1, `viewAny`/`view`/`create`/
  `update`/`delete`/`publish` allow only `super_admin`; every instructor is denied unconditionally.
  The target shape (`architecture.md` §8.3) is "Instructor: only on assigned courses," which needs
  `Course::assignedTo($user)` (§8.4) — Track A's `Course` model isn't on `main` yet. **Do not treat
  an instructor being denied assessment access as a bug** until this is deliberately revisited and
  this line is removed. Likely trigger to revisit: Track A's catalogue block merges to `main`, or
  Phase 8 (Assessment Engine) starts, whichever comes first. See Checkpoint 1 → Deviation 2 below
  for the full reasoning.

- **Answer-key reveal mechanism, decided now so Phase 8 doesn't reinvent it: `QuestionPresenter`,
  not `$hidden` alone and not a generic API Resource class.** `architecture.md` §6.4 and §12.2
  already name a dedicated `QuestionPresenter` as the thing that produces the student-facing payload
  — no generic API Resource class is named anywhere in the docs, so don't introduce one for this
  column. The reveal is conditional on request-time state (`AnswerRevealPolicy` × submission status),
  which a static `$hidden` property cannot express by itself — that's why a presenter, not a
  visibility flag, is the documented mechanism, and why Phase 8 still has to build it.
  **What Checkpoint 3 added underneath that, as defence-in-depth, not a substitute:**
  `QuestionOption::$hidden = ['is_correct']`. This only affects `toArray()`/`toJson()` (and anything
  that funnels through them — the failure mode a new endpoint or a `return $question->load(
  'options')` debug response would hit); it does NOT affect direct attribute access
  (`$option->is_correct`), so Blade views, admin/instructor authoring screens, and grading logic are
  unaffected. When Phase 8 builds `QuestionPresenter`, it will need `makeVisible('is_correct')` (or
  to construct its own array) after checking `AnswerRevealPolicy` and submission state — that call
  is expected and is not working around this default, it's the intended escape hatch for it.

---

## Checkpoint 1 — `assessments` migration + model + factory + policy + enums

**Branch:** `phase/03-assessment-schema`

**Delivered**
- Migration `2026_08_13_100300_create_assessments_table.php` — polymorphic `assessable` (no FK,
  by design), CHECK constraints mirroring three enums, `created_by` nullable FK with `SET NULL`.
- Enums: `AssessmentType` (quiz|test), `AnswerRevealPolicy` (never|after_submit|after_pass),
  `ScoringPolicy` (highest|latest|first).
- Model `Assessment` — fillable excludes `created_by` (ownership field) and the two derived-cache
  columns (`total_marks`, `questions_count`); `scopePublished()`; `assessable()` morphTo;
  `createdBy()` belongsTo.
- Factory `AssessmentFactory` — default state is a published quiz; states for `forCourse()`,
  `unpublished()`, `timed()`.
- Policy `AssessmentPolicy` — deny-by-default; `viewAny`/`view`/`create`/`update`/`delete`/`publish`.
- Tests: `tests/Feature/AssessmentSchemaTest.php` (CHECK constraints, enum/bool casts, polymorphic
  no-FK behaviour, `created_by` mass-assignment guard and `SET NULL` on creator deletion, published
  scope) and `tests/Feature/Authorization/AssessmentPolicyTest.php`.

**Deviation 1 — added a `ScoringPolicy` enum not named in the track brief.** The track brief's enum
list for Phase 3 is `AssessmentType`, `QuestionType`, `AnswerRevealPolicy`, `AttemptStatus` — it
doesn't mention `scoring_policy`. But `architecture.md` §6.4 specifies `scoring_policy` as a fixed
three-value column (`highest|latest|first`), and the shared CLAUDE.md rule is unconditional: "every
status and type is a PHP enum with a matching database CHECK constraint." Treated the specific rule
as authoritative over an apparently-incomplete summary list. Scoped entirely inside the assessments
table already in Checkpoint 1 — not new ground.

**Deviation 2 — `AssessmentPolicy`'s instructor branch is not implemented.** `architecture.md` §8.3
specifies "Admin: all. Instructor: only on assigned courses. Student: none." The instructor branch
needs `Course::assignedTo($user)` (§8.4), and Track A's `Course`/`Module`/`Lesson` models don't
exist on `main` yet (verified via `git ls-tree origin/main -- database/migrations/` — no
`100100`–`100150` block). Built only the admin-all / everyone-else-denied shape for now, which is
the safe direction to fail in, and left an explicit comment + test locking in today's behaviour.
**Follow-up:** revisit once Track A's catalogue models land — likely a Phase 8 concern, since that's
when `AssessmentPolicy` actually gets consumed.

**Deviation 3 — inverted two pre-existing "hasn't built ahead" guards.** `tests/Feature/
FoundationTest.php` and `tests/Feature/IdentitySchemaTest.php` both asserted `assessments` does not
exist, as a Phase 2 guard against building ahead. Now that Track B has created it, both assertions
are updated to expect `assessments` to exist while continuing to guard the tables that belong to
Tracks A and C. This is the same pattern already recorded for `users` between Phase 1 and Phase 2
(`phases.md` deviations) — not a new precedent.

**Gate results**
```
composer check
  pint    : passed
  phpstan : passed, 0 errors (level 8)
  pest    : 154/154 passed, 426 assertions (was 142/142 before this checkpoint)
```
One pre-existing Pest warning (present before this checkpoint too, confirmed by stashing these
changes and re-running) — unrelated to this work, left untouched.

**Not done here (later checkpoints / later phases):** `questions`, `question_options`,
`assessment_attempts`, `attempt_answers`; `QuestionType`, `AttemptStatus` enums; `AttemptPolicy`.

---

## Checkpoint 2 — `questions` migration + model + factory

**Branch:** `phase/03-assessment-schema` (same branch, continues Checkpoint 1)

**Delivered**
- Migration `2026_08_13_100310_create_questions_table.php` — real FK to `assessments`
  (`cascadeOnDelete`, unlike the polymorphic no-FK `assessable`), CHECK constraint mirroring
  `QuestionType`, `negative_marks` defaults to `0` rather than being nullable (grading arithmetic in
  Phase 8 always subtracts it).
- Enum `QuestionType` (single_choice|multiple_choice|true_false|short_answer).
- Model `Question` — fillable excludes `assessment_id` (set via the owning relation, same convention
  already used for `InstructorProfile::user_id`); decimal casts on `marks`/`negative_marks`; `meta`
  cast to array.
- Added `Assessment::questions()` (`hasMany`, ordered by `position`) now that `Question` exists —
  intentionally deferred out of Checkpoint 1 since referencing a not-yet-created class would have
  broken Larastan.
- Factory `QuestionFactory` — default is a 1-mark single-choice question; states for
  `multipleChoice()`, `trueFalse()`, `shortAnswer()` (the last also populates `meta.accepted_answers`,
  since short-answer questions are graded from `meta`, not `question_options`, per FR-ASMT-07).
- Tests: `tests/Feature/QuestionSchemaTest.php` — CHECK constraint, every `QuestionType` accepted,
  cascade delete from parent assessment, `assessment_id` mass-assignment guard, position ordering
  through `Assessment::questions()`, decimal/array casts.

**No deviations this checkpoint** — straightforward build against `architecture.md` §6.4 and the
Phase 3 DoD ("deleting a[n] assessment cascades to questions").

**Gate results**
```
composer check
  pint    : passed
  phpstan : passed, 0 errors (level 8)
  pest    : 164/164 passed, 439 assertions (was 154/154 after Checkpoint 1)
```
Same single pre-existing Pest warning as Checkpoint 1, still unrelated.

**Not done here (later checkpoints / later phases):** `question_options`, `assessment_attempts`,
`attempt_answers`; `AttemptStatus` enum; `AttemptPolicy`.

---

## Checkpoint 3 — `question_options` migration + model + factory + answer-key secrecy

**Branch:** `phase/03-assessment-schema` (same branch, continues Checkpoints 1–2)

**Delivered**
- Migration `2026_08_13_100320_create_question_options_table.php` — FK to `questions`
  (`cascadeOnDelete`), `is_correct` boolean default `false`, `(question_id, position)` index. No
  CHECK constraint needed — no enum-backed column on this table.
- Model `QuestionOption` — fillable excludes `question_id` (owning-relation convention, as before);
  **`$hidden = ['is_correct']`** — see the "Known gaps" entry above for the full reasoning; casts
  `is_correct` to boolean.
- Added `Question::options()` (`hasMany`, ordered by `position`), deferred out of Checkpoint 2 for
  the same reason `Assessment::questions()` was deferred out of Checkpoint 1.
- Factory `QuestionOptionFactory` — default is an incorrect option (distractors are the common case);
  `correct()` state for the answer.
- Tests: `tests/Feature/QuestionOptionSchemaTest.php` — cascade delete (direct, and transitively
  through the parent assessment), `question_id` mass-assignment guard, position ordering, casts, and
  **two tests on the raw model itself**, not on any resource/view (Phase 3 ships neither):
  `toArray()`/`toJson()` never contain `is_correct`, including nested under
  `$question->load('options')->toArray()` — the shape a careless future endpoint would produce — and
  a companion assertion that the value is still directly readable (`$option->is_correct`), so
  grading logic and the eventual presenter aren't blocked by the same default that hides it from
  serialisation.

**Verified the leak-proof test actually catches the regression it's meant to catch**, not just
written to a shape that looks right: temporarily commented out `QuestionOption::$hidden`, reran
`QuestionOptionSchemaTest`, confirmed both serialisation assertions failed
(`Expecting [...] not to have key 'is_correct'`), then reverted and reconfirmed the full gate is
green. Not left in the codebase — this was a one-off manual check, not an automated mutation test.

**Design decision, not a deviation — recorded above under "Known gaps carried forward"** so it's
visible before Phase 8 starts: the answer-key reveal mechanism is `QuestionPresenter` (already named
in the architecture docs), with `$hidden` added now as an automatic baseline underneath it. Neither
"just use `$hidden`" nor "just use a Resource class" is the right one-line answer here — the
combination is.

**Gate results**
```
composer check
  pint    : passed
  phpstan : passed, 0 errors (level 8)
  pest    : 172/172 passed, 455 assertions (was 164/164 after Checkpoint 2)
```
Same single pre-existing Pest warning as Checkpoints 1–2, still unrelated.

**Not done here (later phases):** `assessment_attempts`, `attempt_answers` (blocked on Shashank's
`enrollments` migration, per the track brief — stopping here as instructed); `AttemptStatus` enum;
`AttemptPolicy`; `QuestionPresenter` itself (Phase 8).
