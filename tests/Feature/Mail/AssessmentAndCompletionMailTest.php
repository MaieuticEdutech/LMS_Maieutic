<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Actions\Student\RecordLessonProgress;
use App\Enums\AssessmentType;
use App\Enums\AttemptStatus;
use App\Enums\EnrollmentSource;
use App\Enums\LessonType;
use App\Events\AttemptGraded;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Notifications\AssessmentResultNotification;
use App\Notifications\CourseCompletedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Phase 11 · the last two transactional emails (FR-MAIL-07, AC-33)
|--------------------------------------------------------------------------
|
| These two were specified in Phase 11 and could not be built then: their
| triggers did not exist. `AttemptGraded` arrived with Phase 8 and
| `CourseCompleted` with Phase 9, so the mail layer's own claim — that a new
| email is a notification class plus a listener, inheriting queueing, branding,
| logging and retry — is now testable rather than merely asserted.
|
| AC-33 runs through the whole file: mail must never be able to break the thing
| that triggered it.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create(['name' => 'Ada Student']);

    $this->course = Course::factory()->published()->create(['title' => 'Statistics I']);
    $this->module = Module::factory()->forCourse($this->course)->published()->create();
    $this->lesson = Lesson::factory()->forModule($this->module)->published()
        ->create(['type' => LessonType::Text]);

    $this->enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    $this->gradedAttempt = function (bool $passed = true, AttemptStatus $status = AttemptStatus::Graded): AssessmentAttempt {
        $assessment = Assessment::factory()->create([
            'assessable_type' => Lesson::class,
            'assessable_id' => $this->lesson->getKey(),
            'type' => AssessmentType::Quiz,
            'title' => 'Chapter 1 quiz',
        ]);

        return AssessmentAttempt::factory()->graded($passed)->create([
            'assessment_id' => $assessment->getKey(),
            'user_id' => $this->student->getKey(),
            'enrollment_id' => $this->enrollment->getKey(),
            'status' => $status,
        ]);
    };
});

/*
| ═══════════════ ASSESSMENT RESULT ═══════════════
*/
it('emails a student their result when an attempt is graded', function (): void {
    Notification::fake();

    AttemptGraded::dispatch(($this->gradedAttempt)());

    Notification::assertSentTo($this->student, AssessmentResultNotification::class);
});

it('emails a result for a FAILED attempt too', function (): void {
    Notification::fake();

    AttemptGraded::dispatch(($this->gradedAttempt)(passed: false));

    /*
     * The half of the audience that most needs to hear. Sending only on a pass
     * would leave a student who did not pass with silence, which is the worst
     * available answer to "how did I do?".
     */
    Notification::assertSentTo($this->student, AssessmentResultNotification::class);
});

it('says nothing when an attempt carries no score', function (): void {
    Notification::fake();

    $assessment = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $this->lesson->getKey(),
        'type' => AssessmentType::Quiz,
    ]);

    // Reachable when a grading run failed part-way. An email announcing a null
    // score as "0%" would tell a student they failed something they never sat.
    $attempt = AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'user_id' => $this->student->getKey(),
        'enrollment_id' => $this->enrollment->getKey(),
        'score_percentage' => null,
    ]);

    AttemptGraded::dispatch($attempt);

    Notification::assertNothingSentTo($this->student);
});

it('states plainly when the timer submitted the attempt', function (): void {
    $rendered = (string) (new AssessmentResultNotification('Chapter 1 quiz', 36, false, 'key', ranOutOfTime: true))
        ->toMail($this->student)
        ->render();

    // A student who walked away and later receives a score with no explanation
    // will reasonably think something went wrong.
    expect($rendered)->toContain('submitted automatically when its time ran out');
});

it('rounds the score to something a person would say', function (): void {
    $rendered = (string) (new AssessmentResultNotification('Chapter 1 quiz', 82, true, 'key'))
        ->toMail($this->student)
        ->render();

    expect($rendered)->toContain('82%')
        ->and($rendered)->not->toContain('82.00');
});

it('does not promise a number of attempts it cannot guarantee', function (): void {
    $rendered = (string) (new AssessmentResultNotification('Chapter 1 quiz', 41, false, 'key'))
        ->toMail($this->student)
        ->render();

    // The limit is evaluated when a student starts an attempt, against rules
    // that can change between this being queued and being read.
    expect($rendered)->not->toContain('attempts left')
        ->and($rendered)->not->toContain('attempts remaining');
});

/*
| ═══════════════ COURSE COMPLETED ═══════════════
*/
it('emails a student when they finish a course', function (): void {
    Notification::fake();

    app(RecordLessonProgress::class)->handle($this->enrollment, $this->lesson, completed: true);

    Notification::assertSentTo($this->student, CourseCompletedNotification::class);
});

it('congratulates a student exactly once', function (): void {
    Notification::fake();

    app(RecordLessonProgress::class)->handle($this->enrollment, $this->lesson, completed: true);

    // A recalculation that finds the course still finished must not re-fire.
    // Three congratulations for one course is how automated mail loses trust.
    app(App\Services\Progress\ProgressCalculator::class)->recalculateCourse($this->enrollment->refresh());

    Notification::assertSentToTimes($this->student, CourseCompletedNotification::class, 1);
});

it('promises no certificate, because there is none', function (): void {
    $rendered = (string) (new CourseCompletedNotification('Statistics I', 12))
        ->toMail($this->student)
        ->render();

    /*
     * Certificates are not built in V1. "Your certificate is on its way" would
     * be a promise the product does not keep, and the student has no way to
     * tell it is a mistake.
     */
    expect($rendered)->not->toContain('certificate')
        ->and($rendered)->not->toContain('Certificate');
});

it('reassures a student that access does not end with the course', function (): void {
    $rendered = (string) (new CourseCompletedNotification('Statistics I', 12))
        ->toMail($this->student)
        ->render();

    // Someone who has just finished a course they paid for is exactly the
    // person who wonders whether they are about to lose it.
    expect($rendered)->toContain('stays in your library');
});

it('omits the lesson count rather than stating nought', function (): void {
    $rendered = (string) (new CourseCompletedNotification('Statistics I'))
        ->toMail($this->student)
        ->render();

    expect($rendered)->not->toContain('all 0');
});

/*
| ═══════════════ AC-33 — MAIL CANNOT BREAK ITS TRIGGER ═══════════════
*/
it('queues every notification in the system, without exception', function (): void {
    /*
     * "All mail is queued, never sent synchronously in a request" (phases.md
     * Phase 11, AC-33).
     *
     * Asserted across the whole directory rather than against the two classes
     * this file is about, because the failure mode is a FUTURE notification
     * forgetting the interface — Phase 12's two are still to come. A test that
     * names today's classes cannot catch that, and would pass forever while
     * being wrong.
     */
    $files = glob(app_path('Notifications/*.php')) ?: [];

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $class = 'App\\Notifications\\'.basename($file, '.php');

        expect(is_subclass_of($class, ShouldQueue::class))->toBeTrue(
            "[{$class}] must implement ShouldQueue. A mailable sent inside the "
            .'request blocks the user and can fail the transaction that triggered it.',
        );
    }
});

it('puts both on the mail queue, not the critical one', function (): void {
    /*
     * A congratulations email must never sit in front of an enrollment or a
     * payment. Queue names come from config so renaming one is a config change
     * (§13) — asserted against that config rather than a literal.
     */
    $mailQueue = config()->string('lms.queues.mail');

    expect((new AssessmentResultNotification('Q', 50, false, 'key'))->queue)->toBe($mailQueue)
        ->and((new CourseCompletedNotification('C'))->queue)->toBe($mailQueue);
});

it('records the lesson complete even when the result email cannot be built', function (): void {
    /*
     * AC-33 in its sharpest form. Two listeners hang off AttemptGraded — one
     * records progress, one sends mail. Breaking the mail path must not stop
     * the lesson being marked complete, which is precisely what would happen
     * if they shared a listener.
     */
    $quizLesson = Lesson::factory()->forModule($this->module)->published()
        ->create(['type' => LessonType::Quiz]);

    $assessment = Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $quizLesson->getKey(),
        'type' => AssessmentType::Quiz,
    ]);

    $attempt = AssessmentAttempt::factory()->graded()->create([
        'assessment_id' => $assessment->getKey(),
        'user_id' => $this->student->getKey(),
        'enrollment_id' => $this->enrollment->getKey(),
    ]);

    /*
     * The mail path is genuinely broken, not merely faked: the student is
     * soft-deleted, so the notification listener resolves no recipient and
     * returns having sent nothing. The progress listener reaches the
     * enrollment by a different route and must be entirely unaffected.
     */
    Notification::fake();
    $this->student->delete();

    AttemptGraded::dispatch($attempt);

    Notification::assertNothingSent();

    expect(App\Models\LessonProgress::query()
        ->where('lesson_id', $quizLesson->getKey())
        ->first()?->completed_at)->not->toBeNull();
});
