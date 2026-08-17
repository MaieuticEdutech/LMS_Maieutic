<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| certificates
|--------------------------------------------------------------------------
|
| TRACK A. Design handoff §7 ("Certificates"), FR-PROG-08, AC-31.
|
| TABLE CLASSIFICATION (rule S-5): TENANT-OWNED.
| `number` is COMPOSITE-READY — becomes (organisation_id, number) in V2.
|
| A certificate is a RECORD OF AN AWARD, not a view over enrollments. It gets
| its own table rather than a flag on `enrollments` because of what has to stay
| true about it after the fact:
|
|   FOUR COLUMNS CARRY DECISIONS, NOT JUST DATA:
|
|   enrollment_id — UNIQUE. One award per enrolment, enforced by the database
|       rather than by the issuing code. Course completion can be recalculated,
|       a lesson can be republished, a queue job can be retried; none of those
|       may produce a second certificate for the same enrolment. A unique index
|       is the only version of that rule which survives a race between two
|       workers (the same reasoning as the attempts table's partial index).
|
|   number — the human-facing, verifiable identifier (MAI-CERT-XXXX-XXXX).
|       UNIQUE and never reused. This is the string a student puts on a CV and
|       an employer types into a verification page, so it must be stable for the
|       life of the record and must not be derivable from a sequence — a
|       guessable id would let anyone enumerate every award the organisation has
|       ever made.
|
|   recipient_name — A SNAPSHOT, TAKEN AT ISSUE, AND DELIBERATELY NOT A JOIN.
|       A certificate states what was awarded to whom on a date. If it rendered
|       `users.certificate_name` live, then a learner editing their profile in
|       2027 would silently rewrite a document an employer verified in 2026 —
|       and every already-downloaded copy would disagree with the system of
|       record. Reissuing is a deliberate act, not a side effect of an edit.
|
|   issued_at — separate from `created_at`. The award date is a fact about the
|       achievement and appears on the document; created_at is a fact about the
|       row. Backfilling historic awards must not print today's date.
|
| NO `revoked_at` COLUMN, and not an oversight. Revocation is a real thing an
| organisation eventually needs, but it needs a reason, an actor and an audit
| entry to mean anything — the same shape as RevokeEnrollment. Adding a bare
| nullable timestamp now would invite `->whereNull('revoked_at')` to spread
| through the codebase before the rule exists. It arrives with its Action.
|
| NO PDF PATH. The certificate is rendered from this row on demand. Storing a
| generated file would create a second source of truth that could disagree with
| the row, and would need invalidating every time the branding changed.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();

            /*
             * The enrolment is the subject of the award — it is what ties this
             * student to this course and carries the completion that earned it.
             * user_id and course_id are denormalised alongside it so the
             * student's list and a public verification lookup do not have to
             * join through enrollments, and so the record still reads correctly
             * if an enrolment is later revoked.
             */
            $table->foreignId('enrollment_id')
                ->unique()
                ->constrained('enrollments')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();

            $table->string('number')->unique();

            // Snapshot. See the header — this is the whole reason the table
            // exists rather than a flag on enrollments.
            $table->string('recipient_name');
            $table->string('course_title');

            $table->timestamp('issued_at');

            $table->timestamps();

            // The student's own list, newest first — the only query this table
            // serves at any volume.
            $table->index(['user_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
