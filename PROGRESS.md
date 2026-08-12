# PROGRESS.md — Srivathsa (Track B, and now also Track C)

Checkpoint log for Phase 3. Originally Track B only (`docs/tracks/TRACK-B-SRIVATHSA.md`); as of
2026-08-12, also Track C (`docs/tracks/TRACK-C-SHASHANK.md` — see ownership note immediately below).
One entry per checkpoint: what landed, gate results, and any deviation from the plan worth a future
reader knowing about.

---

# PHASE 3 — FINAL SUMMARY (handoff document, 2026-08-12)

**Read this section first.** Everything below it is the chronological checkpoint-by-checkpoint log —
useful for "why was this decision made," not needed to understand where things stand today. This
section is that.

## Status: all three tracks' schema work is done. Gate G1 is not yet cleared.

> **UPDATE 2026-08-12, after merge — G1 IS NOW CLEARED.** This section was correct when written.
> It has since been superseded by events: PR #1 (Phase 5 backend) and PR #2 (this branch) are both
> merged to `main` at `8848bba`. Verified on the merged tree, not on either branch alone:
> 28 tables, `migrate:fresh` green, Pint clean, Larastan level 8 zero errors,
> **Pest 524/524, 1,093 assertions**. Zero file overlap between the two branches; nothing lost in
> the merge.
>
> The insistence below on not reporting G1 clear until it actually was, was the right call — it
> just happened.
>
> **`planning.md` §21.7 is the live tracker from here on.** This file is the Phase 3 checkpoint
> log: read it for *why* a decision was made, not for current status. Two trackers drift; one wins.

Those are two different facts and worth keeping separate:

- **Done:** all 17 Phase 3 domain tables exist, migrate cleanly, and roll back cleanly, across all
  three tracks. Full quality gate is green.
- **Not yet done:** Gate G1 (`phases.md`/`planning.md`) clears only when all three tracks' migrations
  are **merged to `main`** and `migrate:fresh --seed` is green **on `main`**. Nothing here is on
  `main` yet — see "Git state" below. Don't report G1 as cleared until it actually is.

## Who built what

| Track | Owner | Tables | Status |
|---|---|---|---|
| **A — Domain trunk** | Govind | `categories`, `courses`, `course_instructor`, `modules`, `lessons`, `media_files` | ✅ Complete, merged to `main` (`c036e44`, `43e0134`) |
| **B — Surfaces & assessment** | Srivathsa | `assessments`, `questions`, `question_options`, `assessment_attempts`, `attempt_answers` | ✅ Complete, this branch |
| **C — Infrastructure & commerce** | Srivathsa (handoff from Shashank, confirmed 2026-08-12) | `webhook_events`, `email_logs`, `orders`, `payments`, `enrollments`, `lesson_progress` | ✅ Complete, this branch |

**28 tables total** (7 framework + 4 identity + 17 Phase 3 domain), **15 enums**, **15 models**, **15
factories**, **6 policies** (`AssessmentPolicy`, `OrderPolicy`, `PaymentPolicy`, `EnrollmentPolicy`,
`AttemptPolicy`, plus Track A's four) — three tables (`webhook_events`, `email_logs`,
`lesson_progress`) confirmed by checking architecture.md directly to need none, not assumed.

## Gate status

```
composer check
  pint    : passed
  phpstan : passed, 0 errors (level 8)
  pest    : 412/412 passed, 838 assertions
```
All 24 migrations verified to both run and roll back cleanly (`migrate:rollback --step=24`, then
restored). One pre-existing Pest warning throughout this entire session, confirmed unrelated to any
of this work (reproduced with all Track B/C changes stashed out) but never actually diagnosed —
harmless as far as tested, but genuinely unknown. Worth a look whenever someone has a spare five
minutes; not blocking anything.

## The complete "still open" list

**Deferred authorization branches — both documented in-code, both denying rather than guessing:**
1. `AssessmentPolicy`'s instructor branch (deferred Checkpoint 1) — **now buildable**, `Course` model
   landed on `main` since. Nobody has picked it up.
2. `AttemptPolicy`'s instructor "read within scope" branch (deferred Track B Checkpoint 4) — **not
   yet buildable**, needs a way to resolve an assessment's owning course through the polymorphic
   `assessable` relation (Lesson → Module → Course), which doesn't exist yet.

**Answer-key reveal mechanism not built:** `QuestionPresenter` (named in architecture.md §6.4 and
§12.2) is Phase 8 work. `QuestionOption::$hidden = ['is_correct']` is only the defence-in-depth
baseline built this phase — it blocks accidental serialization, but the actual policy-aware,
authorized reveal (honoring `AnswerRevealPolicy` and submission state) does not exist yet.

**Docs still stale, by explicit instruction, not oversight:** `planning.md` §21.2's ownership table
and `docs/tracks/TRACK-C-SHASHANK.md` both still name Shashank as Track C's sole owner. The
2026-08-12 full handoff (Shashank off all of Phase 3, Srivathsa owns both B and C, Shashank resumes
at Phase 4) is recorded here and nowhere else yet. Whoever reconciles this should treat this file as
the source of truth for what actually happened, not the stale docs.

**The natural Phase 3 convergence task, unblocked but not started:** the track brief calls this out
explicitly — "`DevelopmentSeeder`, `lms:progress:rebuild`, `lms:counters:rebuild` — these need every
model to exist, so they are the natural convergence task" for "whoever finishes first." Every model
now exists. Nobody has built any of the three.

**Git state, as of this summary:**
- Committed locally: `5658345` — "Phase 3: Track C complete + Track B Checkpoints 4-5" (55 files),
  on top of `c07faf9` (the merge of Track A's catalogue slice).
- **Push to `origin/phase/03-assessment-schema` did NOT complete.** It hung and was killed after a
  2-minute timeout; `git ls-remote origin phase/03-assessment-schema` afterward returned nothing,
  confirming the branch never reached `origin`. Read access (`ls-remote`, `fetch`) works fine, so
  this isn't a broad connectivity problem — it looks like the push (a write, requiring
  authentication) is blocked on an interactive credential prompt (Windows Git Credential Manager,
  `credential.helper = helper-selector`) that a non-interactive tool session cannot complete. Same
  root cause `planning.md` §2 already flagged once before ("git cannot prompt for credentials from
  this shell"). **Needs a human to run the push** — from a terminal where the credential prompt can
  actually be answered.
- No PR opened yet — waiting on the push above.

## What to do next (for whoever reads this — Srivathsa or Shashank)

1. Run `git push -u origin phase/03-assessment-schema` from an interactive terminal.
2. Open the PR — 55 files, both Track B and Track C, touches one shared file
   (`app/Providers/AppServiceProvider.php`) — worth the second set of eyes this was written for.
3. Get it merged to `main`, then confirm Gate G1 by running `migrate:fresh --seed` and `composer
   check` **on `main`**, not just on this branch.
4. Update `planning.md` §21.2 and `docs/tracks/TRACK-C-SHASHANK.md` to reflect the actual ownership.
5. Decide who picks up the still-open list above, and when.

---

---

## Ownership note — Track C reassignment (2026-08-12)

**Confirmed directly by Srivathsa in-session, not yet reflected in the committed docs.** Srivathsa
is now the owner of Track C (previously Shashank's — infrastructure, commerce, `enrollments`,
`orders`, `payments`, `webhook_events`, `lesson_progress`, `email_logs`) in addition to Track B.
Confirmed with Shashank directly per Srivathsa.

**What's stale as of this session:** `planning.md` §21.2's ownership table and
`docs/tracks/TRACK-C-SHASHANK.md` both still name Shashank as Track C's owner — checked against
`origin/main` at commit `467bec7` and confirmed unchanged. The doc restructuring that landed in that
same commit range (`440464f`, `467bec7`, "reallocate phases 4-17 by work stream") is unrelated to
this reassignment — it reorganizes all three tracks by round/directory ownership but still names
Shashank throughout. Srivathsa is getting the team to update these docs; this repo state (as of
`c07faf9`) is ahead of what's written down, by explicit instruction, not by assumption. A future
reader who finds `TRACK-C-SHASHANK.md` still says "Shashank" should trust this note and the commit
history over the stale doc, and update the doc rather than being confused by the mismatch.

**What this changes operationally:** all of Track C's Phase 3 checkpoints (C1–C6 below) proceed
under the same one-checkpoint-at-a-time, tests-alongside discipline as Track B. The single-owner
boundary is unaffected by the reassignment — see the next section.

**Update, 2026-08-12, same day — full Phase 3 handoff confirmed.** Srivathsa confirmed directly with
Shashank: Shashank is handing off **all of Phase 3** — both Track B and Track C, all remaining
checkpoints — to Srivathsa. Shashank is not working on anything in Phase 3 and will not touch it; he
picks back up starting Phase 4. This closes the open question raised at the end of Checkpoint C1
(whether Shashank might have local, un-pushed work on Track C tables, `enrollments` in particular) —
there is no risk of collision on `enrollments` or anything else in Track C for the remainder of
Phase 3. No further check for concurrent Track C work is needed before building it.

---

## Known gaps carried forward — check this before assuming this work is done

- **`AssessmentPolicy` has no instructor branch — status changed, not yet fixed.** Since Checkpoint
  1, `viewAny`/`view`/`create`/`update`/`delete`/`publish` allow only `super_admin`; every instructor
  is denied unconditionally. The target shape (`architecture.md` §8.3) is "Instructor: only on
  assigned courses," which needs `Course::assignedTo($user)` (§8.4). **Update:** Track A's `Course`
  model, including `assignedTo()`, merged to `main` in `43e0134` and is now available on this branch
  (merged in `c07faf9`) — the blocker that justified deferring this is gone. **Still not implemented
  in this session** because wiring it wasn't part of the approved checkpoint scope; this is now
  purely a scheduling choice, not a dependency wait. Likely trigger to actually do it: explicit
  go-ahead, or Phase 8 start, whichever comes first.

- **`enrollments` is now Srivathsa's table to build (Track C, Checkpoint C5), but
  `EnrollmentAccessService`/`GrantEnrollment` remain Govind's single-owner components regardless.**
  The Track C reassignment does not change this — `TRACK-A-GOVIND.md` and `TRACK-C-SHASHANK.md` both
  independently describe this as a deliberate split ("he builds the storage, you own the meaning"),
  and nothing about who owns Track C changes who owns the service. Building `enrollments` well is in
  scope; building or modifying `EnrollmentAccessService`/`GrantEnrollment` is not, ever, without
  Govind's explicit involvement.

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

---

## Merge — Track A catalogue + doc restructuring into `phase/03-assessment-schema`

Between Checkpoint 3 and Checkpoint C1, `origin/main` moved from `2db3258` to `467bec7`: Track A's
full catalogue slice landed (`categories`, `courses`, `course_instructor`, `modules`, `lessons`,
`media_files` — migrations, models, policies, factories, plus an `EnrollmentAccessService`
skeleton), alongside the round/directory-ownership doc restructuring covered in the ownership note
above. Pulled into local `main` (clean fast-forward), then merged into this branch.

**Conflict, expected and predicted before it happened:** `tests/Feature/FoundationTest.php` and
`tests/Feature/IdentitySchemaTest.php` — both branches had independently extended the same
"hasn't built ahead" / "hasn't created another track's tables" guards. Verified via a non-destructive
`git merge-tree` dry run before touching anything, confirmed the conflict was real, then resolved
additively per instruction: adopted Track A's more exhaustive per-track table lists, corrected
`assessments`/`questions`/`question_options` to `toBeTrue()` (built on this branch, Checkpoints 1–3)
where Track A's version — written before those existed — still expected them false. Full resolved
diff shown before committing (`c07faf9`). No other conflicts.

Post-merge: `migrate:fresh --seed` runs all 16 tables (7 framework + 4 identity + 6 catalogue +
3 of Track B's 5) cleanly together for the first time; `composer check` green — 225/225, 574
assertions.

---

## Checkpoint C1 — `webhook_events` migration + model + factory + `WebhookStatus` enum

**Branch:** `phase/03-assessment-schema` (same branch — Track C work continues here rather than a
separate `phase/03-commerce-schema`, since both tracks now converge on one owner; revisit this
choice if it causes review friction)

**Delivered**
- Migration `2026_08_13_100220_create_webhook_events_table.php` — zero cross-track dependency, no
  FK to anything. `UNIQUE(event_id)` (the idempotency key — Phase 3 DoD item, proven by a throw-test,
  not just read off the migration), CHECK constraint mirroring `WebhookStatus`, index on `status`
  (backs the "webhook_events.status = failed" monitoring alert named in `architecture.md` §13).
- Enum `WebhookStatus` (received|processing|processed|failed|ignored) — the gap flagged before
  building this checkpoint, same reasoning as `ScoringPolicy` in Checkpoint 1: a fixed-value column
  named in `architecture.md` §6.4 but absent from the track brief's enum list.
- Model `WebhookEvent` — every writable column is fillable (no untrusted user-input path touches
  this table at all — it's written only by the signature-verified webhook endpoint and
  `ProcessPaymentWebhook`, per `architecture.md` §11/§13, so the mass-assignment concerns that shape
  other models' `$fillable` don't apply here).
- Factory `WebhookEventFactory` — default is a freshly received Razorpay `payment.captured` event;
  `processed()` and `failed()` states.
- Tests: `tests/Feature/WebhookEventSchemaTest.php` — the `UNIQUE(event_id)` throw-test, CHECK
  constraint, every `WebhookStatus` accepted, casts, failure-state shape.

**Checked before building, as instructed: no policy for `webhook_events`.** Searched
`architecture.md` §11 (payment webhook flow) and §13 (queue architecture) for any access-control or
user-facing mention of `webhook_events` — found none. It's written by an unauthenticated,
signature-verified endpoint and read only by a queue job; no admin/instructor/student screen touches
it in Phase 3 (or anywhere named so far). The track brief's silence on a `WebhookEventPolicy` is
confirmed intentional, not an oversight — matches the reasoning already applied to `AssessmentPolicy`
not needing option-level policies. Same check still owed for `email_logs` (C2) and `lesson_progress`
(C6) before those checkpoints.

**Gate results**
```
composer check
  pint    : passed
  phpstan : passed, 0 errors (level 8)
  pest    : 234/234 passed, 591 assertions (was 225/225 after the merge)
```
Same single pre-existing Pest warning as every earlier checkpoint, still unrelated.

**Build-ahead guards updated again** (`FoundationTest.php`, `IdentitySchemaTest.php`) — same pattern
as every table this session: `webhook_events` moved from the "not yet built" list to "delivered."

**Not done here (later checkpoints):** `email_logs`, `orders`, `payments`, `enrollments`,
`lesson_progress`; `EmailStatus`, `OrderStatus`, `PaymentStatus`, `EnrollmentStatus`,
`EnrollmentSource`, `ProgressStatus`, `CompletionSource` enums; `OrderPolicy`, `PaymentPolicy`,
`EnrollmentPolicy`.

---

## Checkpoint C2 — `email_logs` migration + model + factory + `EmailStatus` enum

**Branch:** `phase/03-assessment-schema` (continues C1)

**Delivered**
- Migration `2026_08_13_100410_create_email_logs_table.php` — zero cross-track dependency, no FK to
  `users` (deliberate: a purchase-created account may not exist yet when the first email — e.g.
  `WelcomeAndActivate` — is sent, and a log entry must not depend on the row it might be describing).
  CHECK constraint mirroring `EmailStatus`.
- Enum `EmailStatus` (queued|sent|failed) — same reasoning as `WebhookStatus`/`ScoringPolicy`: named
  as a fixed-value column in `architecture.md` §6.4 but absent from the track brief's enum list.
- Model `EmailLog` — every writable column fillable, same reasoning as `WebhookEvent` (written only
  by `SendMailJob`, never by user input).
- Factory `EmailLogFactory` — default is a freshly queued `VerifyEmail`; `sent()` and `failed()`
  states.
- Tests: `tests/Feature/EmailLogSchemaTest.php` — CHECK constraint, every `EmailStatus` accepted,
  an email logged with no corresponding user account (proves the no-FK design decision actually
  works, not just that it compiles), casts, failure-state shape.

**Judgment call: no index beyond the primary key**, unlike `webhook_events`'s `status` index in C1.
That one was justified by an explicit line in `architecture.md` §13 ("webhook_events.status = failed
alert"); no equivalent line exists for `email_logs` anywhere in §13, §14, or §17's monitoring
baseline. Rather than guess at a query pattern, left it unindexed and noted in the migration comment
that this is a Phase 13 (Reporting) decision once real query patterns are known — consistent with
not inventing beyond what's specified.

**Checked before building, as instructed: no policy for `email_logs`.** Searched `architecture.md`
§13 (queue architecture) and §14 (email architecture) — found no access-control or user-facing
mention. Written only by `SendMailJob`; no admin/user screen touches it in Phase 3. Same conclusion
and same reasoning as `webhook_events` in C1. One check left before C6: `lesson_progress`.

**Gate results**
```
composer check
  pint    : passed
  phpstan : passed, 0 errors (level 8)
  pest    : 241/241 passed, 602 assertions (was 234/234 after C1)
```
Same single pre-existing Pest warning as every earlier checkpoint, still unrelated.

**Build-ahead guards updated again**, same pattern as every table this session: `email_logs` moved
from the "not yet built" list to "delivered" in both `FoundationTest.php` and
`IdentitySchemaTest.php`.

**Not done here (later checkpoints):** `orders`, `payments`, `enrollments`, `lesson_progress`;
`OrderStatus`, `PaymentStatus`, `EnrollmentStatus`, `EnrollmentSource`, `ProgressStatus`,
`CompletionSource` enums; `OrderPolicy`, `PaymentPolicy`, `EnrollmentPolicy`.

---

## Checkpoint C3 — `orders` migration + model + factory + `OrderPolicy` + `OrderStatus` enum

**Branch:** `phase/03-assessment-schema` (continues C1–C2)

**Delivered**
- Migration `2026_08_13_100200_create_orders_table.php` — real FK to `courses` (`restrictOnDelete`,
  since `course_id` is not nullable and an order must always name what was purchased); `user_id`
  nullable with `nullOnDelete` (a buyer may not have an account yet, and deleting one later must not
  delete their purchase history — NFR-DATA-05, same reasoning as `audit_logs.user_id`). `UNIQUE
  (order_number)`, `UNIQUE(gateway_order_id)` (nullable-safe), CHECK on `OrderStatus`, and three
  non-negative CHECK constraints on the money columns.
- Enum `OrderStatus` (created|pending|paid|failed|cancelled|refunded).
- Model `Order` — fillable excludes `user_id`, `status` and all three money columns (NFR-SEC-07,
  same convention as `Course::$fillable` excluding `status`/`price_amount`); `buyer_email` normalised
  on write (same mutator pattern as `User`); `subtotal()`/`discount()`/`total()` read-only `Attribute`
  accessors returning `Money` value objects, mirroring `Course::price()` exactly — money is stored as
  a raw `bigint` cast to `integer`, never cast directly to `Money` (ADR-007).
- Factory `OrderFactory` — default is a resolved buyer, freshly created order, nothing charged;
  states `forGuestBuyer()`, `pending()`, `paid()`, `failed()`, `discounted()`.
- Policy `OrderPolicy` — `viewAny`/`view` only (this table is never created or updated through a
  policy-gated action; checkout and webhook processing write it directly, Phase 12). The FR-INS-10
  instructor-denial check runs first and unconditionally, before the super-admin check, so it can't
  be bypassed by broadening `isSuperAdmin()` later or by an instructor who is also the buyer.
- Tests: `tests/Feature/OrderSchemaTest.php` (CHECK constraints, unique constraints including the
  nullable-safe `gateway_order_id` case, non-negative money, buyer survives user deletion, course
  deletion is restricted when orders exist, mass-assignment guards, email normalisation, `Money`
  accessors) and `tests/Feature/Authorization/OrderPolicyTest.php` (admin sees all, student sees own
  only, **instructor denied even for their own order** — the specific scenario FR-INS-10 exists to
  prevent).

**Judgment call: non-negative CHECK constraints on the three money columns, not the stricter `> 0`
`courses.price_amount` uses.** Architecture.md doesn't state a positivity requirement for order
amounts the way ADR-014 does for course price, and a fully-discounted order legitimately totals
zero. Chose the invariant that's unconditionally true (money isn't negative) over guessing at a
business rule that belongs to Phase 12's checkout Action.

**Gate results**
```
composer check
  pint    : passed
  phpstan : passed, 0 errors (level 8)
  pest    : 268/268 passed, 640 assertions (was 241/241 after C2)
```
Same single pre-existing Pest warning as every earlier checkpoint, still unrelated.

**Build-ahead guards updated again**, same pattern: `orders` moved from "not yet built" to
"delivered" in both `FoundationTest.php` and `IdentitySchemaTest.php`.

**Not done here (later checkpoints):** `payments`, `enrollments`, `lesson_progress`; `PaymentStatus`,
`EnrollmentStatus`, `EnrollmentSource`, `ProgressStatus`, `CompletionSource` enums; `PaymentPolicy`,
`EnrollmentPolicy`.

**Reminder queued for when C5 (`enrollments`) lands, per explicit instruction:** tell Srivathsa that
Track B's Checkpoints 4–5 (`assessment_attempts`, `attempt_answers`) are unblocked, and propose
whether to do them immediately in the same session or as a separate pass — do not decide this
unilaterally, surface the choice.

---

## Checkpoint C4 — `payments` migration + model + factory + `PaymentPolicy` + `PaymentStatus` enum

**Branch:** `phase/03-assessment-schema` (continues C1–C3)

**Delivered**
- Migration `2026_08_13_100210_create_payments_table.php` — real FK to `orders` (`restrictOnDelete`,
  same reasoning as `orders.course_id`: `order_id` isn't nullable, a payment record must always name
  its order). **`UNIQUE(gateway_payment_id)`** — the Phase 3 DoD headline item for this checkpoint,
  proven by a throw-test, the capture-side counterpart to `webhook_events.event_id` on the receiving
  side. CHECK on `PaymentStatus`, non-negative CHECK on `amount` and `refunded_amount` (same pattern
  as `orders`).
- Enum `PaymentStatus` (created|authorized|captured|failed|refunded).
- Model `Payment` — fillable excludes `order_id` (owning-relation convention), `status`, `amount`,
  `refunded_amount` (NFR-SEC-07, same convention as `Order`). Two `Money` accessors, deliberately
  named `money()`/`refundMoney()` rather than reusing the column names `amount`/`refunded_amount` —
  naming them identically to the raw columns would silently override those columns' `integer` casts
  instead of adding a separate computed attribute (a mistake that would have been invisible until
  something read the wrong type).
- Added `Order::payments()` (`hasMany`), deferred out of C3 for the same reason
  `Assessment::questions()` was deferred out of Track B Checkpoint 1 — the target class didn't exist
  yet.
- Factory `PaymentFactory` — default is a freshly created, uncaptured attempt; `captured()`,
  `failed()`, `refunded()` states.
- Policy `PaymentPolicy` — identical shape to `OrderPolicy`: `viewAny`/`view` only, FR-INS-10
  instructor-denial checked first and unconditionally.
- Tests: `tests/Feature/PaymentSchemaTest.php` (unique `gateway_payment_id` throw-test, CHECK
  constraints, non-negative money, order deletion restricted when payments exist, mass-assignment
  guards, the owning-relation listing, `Money` accessors) and
  `tests/Feature/Authorization/PaymentPolicyTest.php` (admin/student/instructor, including instructor
  denied for a payment on their own order, and a student denied on a guest/no-buyer order's payment).

**Bug caught before it ran, not after:** the first draft of the "order deletion is restricted" test
called `$order->forceDelete()`, copying the pattern from C3's course-deletion test. `Order` has no
`SoftDeletes` trait (unlike `Course`), so `forceDelete()` doesn't exist on it — would have been a
fatal error, not the intended `QueryException`. Caught by checking the model before running the test,
not by a failing gate; fixed to `$order->delete()`, which is already a hard delete here.

**Gate results**
```
composer check
  pint    : passed
  phpstan : passed, 0 errors (level 8)
  pest    : 288/288 passed, 667 assertions (was 268/268 after C3)
```
Same single pre-existing Pest warning as every earlier checkpoint, still unrelated.

**Build-ahead guards updated again**, same pattern: `payments` moved from "not yet built" to
"delivered" in both `FoundationTest.php` and `IdentitySchemaTest.php`.

**Not done here (later checkpoints):** `enrollments`, `lesson_progress`; `EnrollmentStatus`,
`EnrollmentSource`, `ProgressStatus`, `CompletionSource` enums; `EnrollmentPolicy`.

---

## Checkpoint C5 — `enrollments` migration + model + factory + `EnrollmentPolicy` + enums

**Branch:** `phase/03-assessment-schema` (continues C1–C4)

**Delivered**
- Migration `2026_08_13_100230_create_enrollments_table.php` — real FKs to `courses`, `users`
  (both `restrictOnDelete`, not nullable), `orders`/`last_lesson_id`/`granted_by`/`revoked_by`
  (all nullable, `nullOnDelete`). **`UNIQUE(user_id, course_id)`** — the single most load-bearing
  constraint in this entire track, proven by a throw-test, plus companion tests proving it does
  *not* over-constrain (same student, different courses; different students, same course both
  succeed). CHECK on `EnrollmentStatus`/`EnrollmentSource`, plus a `progress_percentage BETWEEN 0
  AND 100` range check.
- Enums `EnrollmentStatus` (active|suspended|completed|expired|refunded), `EnrollmentSource`
  (purchase|admin_grant|import).
- Model `Enrollment` — storage only, no access logic. Fillable is deliberately narrow: excludes
  `user_id`/`course_id` (identity), `status`/`source` (access-relevant state), `granted_by`/
  `revoked_by` (audit trail), `progress_percentage`/`completed_lessons_count` (rebuildable caches) —
  the same NFR-SEC-07 exclusion pattern every other model in this codebase uses, applied
  comprehensively here because this table is the access-control record itself. `scopeActive()` is
  explicitly documented as a status filter, **not** the access gate — `EnrollmentAccessService::
  grantsAccess()` (Govind's) is that, and the docblock says so twice so it can't be mistaken for a
  shortcut.
- Factory `EnrollmentFactory` — default is an active purchase-sourced enrollment linked to a paid
  order; `adminGranted()`, `suspended()`, `completed()`, `expired()`, `revoked()` states.
- Policy `EnrollmentPolicy` — `viewAny`/`view`/`grant`/`revoke`, matching architecture.md §8.3
  exactly ("Admin only for write; student reads own; instructor reads within assigned course").
  **The instructor branch is fully implemented here**, using `Course::isAssignedTo()` — unlike
  `AssessmentPolicy`'s deferred instructor branch, Track A's `Course` model is now on this branch
  (merged before C1), so there was no reason to defer it.
- Tests: `tests/Feature/EnrollmentSchemaTest.php` (the unique-pair throw-test and its two
  non-over-constraining companions, CHECK constraints, percentage range, RESTRICT on user/course
  deletion, SET NULL on order/last-lesson deletion, mass-assignment guards, the scope) and
  `tests/Feature/Authorization/EnrollmentPolicyTest.php` (admin, student-own-only, and
  instructor-within-assigned-course using a real `course_instructor` attachment, not a mock).

**Judgment calls, both low-ambiguity, noted rather than stopped on:**
1. `progress_percentage BETWEEN 0 AND 100` — a stricter CHECK than the "non-negative only" pattern
   used for money. Justified differently: a percentage is 0–100 by definition, not by business rule,
   so this doesn't carry the same risk of guessing at Phase 12 logic that the money columns did.
2. `Enrollment::$fillable` has no explicit spec to check against (architecture.md lists columns, not
   mass-assignment rules) — extended the established NFR-SEC-07 pattern as far as it reasonably goes
   for this specific table, on the reasoning that `GrantEnrollment` is expected to set fields
   explicitly by name or via `forceFill()` regardless, the same way every other Action in this
   codebase already does for its model's excluded fields.

**Gate results**
```
composer check
  pint    : passed
  phpstan : passed, 0 errors (level 8)
  pest    : 326/326 passed, 731 assertions (was 288/288 after C4)
```
Same single pre-existing Pest warning as every earlier checkpoint, still unrelated.

**Build-ahead guards updated again**, same pattern: `enrollments` moved from "not yet built" to
"delivered" in both `FoundationTest.php` and `IdentitySchemaTest.php`.

**THE REMINDER, DELIVERED (per the instruction queued since C3):** Track B's Checkpoints 4–5
(`assessment_attempts`, `attempt_answers`) are now unblocked — `enrollments` exists. Per your
2026-08-12 instruction to proceed through C5→C6→Track B Checkpoint 4→Checkpoint 5 back to back
without stopping for approval, this is being noted here rather than surfaced as a stop-and-decide
moment — the "do them now or as a separate pass" choice was already made when that instruction was
given.

**Not done here (later checkpoint):** `lesson_progress`; `ProgressStatus`, `CompletionSource` enums.

---

## Checkpoint C6 — `lesson_progress` migration + model + factory + enums

**Branch:** `phase/03-assessment-schema` (continues C1–C5) — **Track C complete after this checkpoint.**

**Delivered**
- Migration `2026_08_13_100400_create_lesson_progress_table.php` — real FKs to `enrollments`
  (`cascadeOnDelete`, explicitly specified in architecture.md §6.4) and `lessons` (`cascadeOnDelete`,
  same reasoning extended — a progress row is meaningless without the lesson it describes);
  denormalised `user_id` (`restrictOnDelete`, though in practice `enrollments.user_id`'s own
  RESTRICT fires first for any row reachable through a live enrollment). **`UNIQUE(enrollment_id,
  lesson_id)`** — the Phase 3 DoD headline item for this checkpoint, the concurrency guarantee that
  makes a progress write a safe upsert (FR-PROG-14, AC-32), proven by a throw-test. CHECK on
  `ProgressStatus`; CHECK on `CompletionSource` written to also permit `NULL` (a not-yet-completed
  row legitimately has no completion source).
- Enums `ProgressStatus` (not_started|in_progress|completed), `CompletionSource`
  (manual|video|assessment|download).
- Model `LessonProgress` — storage only, no completion or recalculation logic (that's
  `RecordLessonProgress`, Phase 9, Govind's). Fillable excludes the three identity columns
  (owning-relation convention) and `status`/`completion_source` (monotonic-guard state — "completed
  never regresses to in_progress," FR-PROG-14 — enforced by the action, not expressible via
  `$fillable` alone, so excluded rather than trusted).
- Added `Enrollment::lessonProgress()` (`hasMany`), same deferred-until-the-target-exists pattern as
  every other relation added this session.
- Factory `LessonProgressFactory` — default is a not-started row; `inProgress()`, `completed()`
  (parameterised by `CompletionSource`) states.
- Tests: `tests/Feature/LessonProgressSchemaTest.php` — the unique-pair throw-test and its
  non-over-constraining companion, both CHECK constraints (including the null-permitting one),
  CASCADE from both enrollment and lesson deletion, RESTRICT on direct user deletion (isolated from
  `enrollments.user_id`'s own RESTRICT by using an unrelated user/enrollment pair, so the test proves
  `lesson_progress`'s own constraint specifically), mass-assignment guards, the owning relation, casts.

**Checked before building, confirmed no policy needed:** searched architecture.md §17 (Progress
tracking architecture) — no policy or access-control mention for `lesson_progress`, and it isn't
named in §8.3's policy table either. Written only by `RecordLessonProgress`; no admin/student/
instructor screen touches the table directly in Phase 3. Same conclusion as `webhook_events` and
`email_logs`, now confirmed for all three tables this track builds with no dedicated policy.

**Gate results**
```
composer check
  pint    : passed
  phpstan : passed, 0 errors (level 8)
  pest    : 348/348 passed, 756 assertions (was 326/326 after C5)
```
Same single pre-existing Pest warning as every earlier checkpoint, still unrelated. No gate failures
this checkpoint — first one this session that passed clean on the first `composer check` run.

**Build-ahead guards simplified, not just updated:** both `FoundationTest.php` and
`IdentitySchemaTest.php` now assert all six Track C tables as a single `toBeTrue()` block (Track C is
complete) — only Track B's `assessment_attempts`/`attempt_answers` remain in the "not yet built"
lists.

**TRACK C IS COMPLETE.** All six migrations, six enums (`WebhookStatus`, `EmailStatus`,
`OrderStatus`, `PaymentStatus`, `EnrollmentStatus`, `EnrollmentSource`, `ProgressStatus`,
`CompletionSource` — eight, not six; two more than the track brief named, both flagged as deviations
when built), six models, six factories, three policies (`OrderPolicy`, `PaymentPolicy`,
`EnrollmentPolicy` — the other three tables confirmed to need none). Continuing directly into Track
B's remaining checkpoints per instruction, no stop here.

---

## Track B Checkpoint 4 — `assessment_attempts` migration + model + factory + `AttemptPolicy`

**Branch:** `phase/03-assessment-schema` (continues all prior checkpoints) — **unblocked this session**
by C5.

**Delivered**
- Migration `2026_08_13_100330_create_assessment_attempts_table.php` — real FKs to `assessments`,
  `users`, `enrollments` (all `restrictOnDelete` — deliberately narrower than the assessment →
  questions → options cascade chain; the Phase 3 DoD scopes cascading to questions/options only, and
  a deleted assessment must not retroactively erase grading history). `ulid` unique URL handle.
  **The partial unique index** (`assessment_attempts_one_in_progress ON (assessment_id, user_id)
  WHERE status = 'in_progress'`) — the single most load-bearing constraint in this entire track,
  proven by a throw-test plus four companion tests proving it does *not* over-constrain (a new
  attempt is allowed once the prior one leaves in-progress, for each of the four terminal statuses;
  different students/different assessments both allowed concurrently). `UNIQUE(assessment_id,
  user_id, attempt_number)`, CHECK on `AttemptStatus`.
- Enum `AttemptStatus` (in_progress|submitted|graded|expired|abandoned).
- Model `AssessmentAttempt` — fillable as minimal as `Enrollment`'s, for the same reason: identity,
  lifecycle state, and grading results (`score_marks`/`max_marks`/`score_percentage`/`is_passed`) are
  all excluded, the last group specifically because `GradingService` (Phase 8) is the only code
  permitted to write them (NFR-SEC-21). `question_order` excluded too — the FR-ASMT-18 snapshot is
  set once at creation and is immutable after. `getRouteKeyName()` returns `ulid`, matching
  `Course::getRouteKeyName()`'s `slug` pattern.
- Added `Assessment::attempts()` (`hasMany`), same deferred-until-the-target-exists pattern as every
  other relation this session.
- Factory `AssessmentAttemptFactory` — default is a fresh, untimed, in-progress first attempt;
  `submitted()`, `graded(bool $passed)`, `expired()`, `abandoned()` states.
- Policy `AttemptPolicy` — `start`/`answer`/`submit`/`review`, matching architecture.md §8.3 exactly
  ("Owner only for write; instructor/admin read within scope"). `start()` is deliberately coarse
  (role + published check only) — FR-ASMT-09's fuller conditions (enrolled, attempt count below
  limit) need `StartAttempt` (Phase 8) to resolve the assessment's owning course and consult
  `EnrollmentAccessService`, the same policy/Action split this codebase already uses everywhere
  (architecture.md §8.2). **Instructor "read within scope" is deferred**, same reasoning as
  `AssessmentPolicy`'s deferred branch, but for a different underlying cause: there's no direct
  `course_id` to check `isAssignedTo()` against (unlike `Enrollment`) — resolving it means walking
  the polymorphic `assessable` relation (Lesson → Module → Course), which doesn't exist yet.

**BUG FOUND AND FIXED — a real gate failure, not a documentation update.** Three `AttemptPolicyTest`
assertions failed (`false` where `true` was expected, no exception). Root cause: **`AssessmentAttempt`
→ `AttemptPolicy` breaks Laravel's naming-convention policy auto-discovery**, which looks for
`AssessmentAttemptPolicy`. Every other policy this session (`AssessmentPolicy`, `OrderPolicy`,
`PaymentPolicy`, `EnrollmentPolicy`) happens to follow the `{Model}Policy` convention and auto-
resolves fine — `AttemptPolicy` is the one place the track brief's chosen name (matching
architecture.md §8.3's own table) doesn't match its model's name. Fixed by registering it explicitly:
`AppServiceProvider::configureAuthorization()` now calls `Gate::policy(AssessmentAttempt::class,
AttemptPolicy::class)`. Confirmed this file isn't owned by any track in CLAUDE.md's shared-files
table before editing it (only `bootstrap/app.php` is Govind's).

A second, related issue surfaced fixing the first: **`$user->can('start', $assessment)` resolves the
WRONG policy** even with the registration above, because Laravel picks a policy by the *argument's*
class — `Assessment` → `AssessmentPolicy`, which has no `start` method. The fix is the array-argument
form: `$user->can('start', [AssessmentAttempt::class, $assessment])`. Documented directly in
`AttemptPolicy`'s docblock (not just here) since Phase 8's `StartAttempt` action will need to call it
the same way.

**Added `tests/Feature/PolicyRegistrationTest.php`**, mirroring Track A's
`Catalogue/PolicyRegistrationTest.php` pattern (`Gate::getPolicyFor()` resolves non-null for each
model, plus a "routes through the real policy, not a default" sanity check) — written *after*
discovering the failure mode firsthand, specifically to make this exact regression impossible to
reintroduce quietly. Covers `Assessment`, `AssessmentAttempt`, `Order`, `Payment`, `Enrollment`.

**Gate results**
```
composer check
  pint    : passed
  phpstan : passed, 0 errors (level 8)
  pest    : 389/389 passed, 808 assertions (was 348/348 after C6) — first run of this checkpoint
            failed 5/384 (3 real policy-registration bug, 2 expected guard updates); all green
            after the fix above.
```
Same single pre-existing Pest warning throughout, still unrelated.

**Build-ahead guards updated again**, same pattern: `assessment_attempts` moved from "not yet built"
to "delivered."

**Not done here (next checkpoint):** `attempt_answers`.

---

## Track B Checkpoint 5 — `attempt_answers` migration + model + factory

**Branch:** `phase/03-assessment-schema` — **the last Phase 3 migration. Both tracks complete after
this checkpoint.**

**Delivered**
- Migration `2026_08_13_100340_create_attempt_answers_table.php` — `attempt_id` CASCADES (explicitly
  specified, architecture.md §6.4); `question_id` RESTRICTs (judgment call — not specified either
  way in the docs, chosen for the same "academic record, not disposable" reasoning already applied
  to `assessment_attempts.assessment_id`). **`UNIQUE(attempt_id, question_id)`** — Phase 3 DoD item,
  proven by throw-test. No CHECK constraints — no enum-backed column on this table.
- Model `AttemptAnswer` — fillable excludes `attempt_id`/`question_id` (owning-relation convention)
  and `is_correct`/`marks_awarded` (grading results, `GradingService` only); `selected_option_ids`,
  `answer_text`, `answered_at` ARE fillable — the student's own submitted content, same shape as
  `Order`'s buyer fields being fillable while its money columns are not. **`is_correct` is
  deliberately NOT `$hidden`**, the explicit contrast with `QuestionOption::$hidden` documented in
  both the migration and the model: this column is the graded *result* of a specific submitted
  answer, produced after submission, and is exactly what a review screen is meant to eventually show
  (subject to `AnswerRevealPolicy`) — hiding it would work against its own purpose. Tested directly:
  a dedicated test proves `toArray()`/`toJson()` *do* include `is_correct` here, the mirror image of
  the `QuestionOption` leak-proof test from Checkpoint 3.
- Added `AssessmentAttempt::answers()` (`hasMany`), same deferred pattern as every relation this
  session.
- Factory `AttemptAnswerFactory` — default is an ungraded single-choice answer; `shortAnswer()`,
  `graded(bool $correct, float $marksAwarded)` states.
- Tests: `tests/Feature/AttemptAnswerSchemaTest.php` — unique-pair throw-test, CASCADE from attempt
  deletion, RESTRICT on question deletion, mass-assignment guards for both directions (grading
  fields refused, student-content fields allowed — tested as a pair, not just the refusal half), the
  `is_correct`-is-visible test, the owning relation, casts.

**Bug caught before it ran, not after (third time this pattern has come up this session):** the
first draft of the attempt-deletion test called `$attempt->forceDelete()`, copying the pattern from
earlier cascade tests. `AssessmentAttempt` has no `SoftDeletes` trait, so that method doesn't exist
on it. Caught by checking the model before running the test; fixed to `$attempt->delete()`.

**Additional Phase 3 DoD verification, not tied to this checkpoint specifically but completed here
since it's the natural point to confirm it:** `php artisan migrate:rollback --step=24` — all 24
migrations (7 framework + 4 identity + 6 catalogue + 5 Track B + 6 Track C) roll back cleanly with no
errors, satisfying phases.md's Phase 3 testing requirement "every migration runs and rolls back
cleanly." Database restored to fully migrated and seeded state afterward.

**Gate results**
```
composer check
  pint    : passed
  phpstan : passed, 0 errors (level 8)
  pest    : 412/412 passed, 838 assertions (was 389/389 after Checkpoint 4)
```
Same single pre-existing Pest warning throughout, still unrelated.

**Build-ahead guards retired, not just updated:** `attempt_answers` was the last table either track
owed. `FoundationTest.php`'s guard now asserts every Phase 3 table exists (no more "not yet built"
half — Phase 4 introduces no new tables). `IdentitySchemaTest.php`'s negative guard ("has not created
another track's tables") had nothing left to assert absent, so it was converted to a positive
counterpart ("creates every Track B and Track C table") rather than left as a vestigial empty-dataset
test — same information, expressed the way that's actually true now.

---

## PHASE 3 — COMPLETE (both tracks, this session)

17 domain tables (6 catalogue/Track A + 5 Track B + 6 Track C) + 7 framework + 4 identity = 28 tables
total, all migrating and rolling back cleanly. 15 enums across the three tracks (4 named in the
Track B brief + 1 added; 6 named in the Track C brief + 2 added — see each checkpoint's deviation
notes for why). 15 models, 15 factories, 6 policies (`AssessmentPolicy`, `OrderPolicy`,
`PaymentPolicy`, `EnrollmentPolicy`, `AttemptPolicy`, plus Track A's four — three tables confirmed,
not assumed, to need none: `webhook_events`, `email_logs`, `lesson_progress`). 412 tests, 838
assertions, Pint clean, Larastan level 8 zero errors throughout.

**Everything still open, for whoever picks this up next:**
- `AssessmentPolicy`'s instructor branch (deferred, Checkpoint 1 — now buildable, `Course` exists)
- `AttemptPolicy`'s instructor "read within scope" branch (deferred, Checkpoint 4 — needs polymorphic
  `assessable` → owning-course resolution, doesn't exist yet)
- `QuestionPresenter` (Phase 8) — the actual answer-reveal mechanism; `$hidden` is only the baseline
- Docs still stale: `planning.md` §21.2 and `TRACK-C-SHASHANK.md` don't yet reflect the Track C
  handoff to Srivathsa (confirmed 2026-08-12, recorded in this file, not yet in those files)
- Not yet committed or pushed — everything above is on `phase/03-assessment-schema`, uncommitted
  changes pending
