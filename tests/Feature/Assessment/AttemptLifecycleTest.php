<?php

declare(strict_types=1);

use App\Actions\Assessment\SaveAnswer;
use App\Actions\Assessment\StartAttempt;
use App\Actions\Assessment\SubmitAttempt;
use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\AttemptStatus;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\QuestionType;
use App\Events\AttemptGraded;
use App\Exceptions\AttemptNotAllowedException;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AttemptAnswer;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Services\Enrollment\EnrollmentAccessService;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| Phase 8 · The attempt lifecycle (AC-24, AC-25, AC-26, FR-ASMT-09 … 12)
|--------------------------------------------------------------------------
|
| Start, answer, submit. Every rule here is server-side and every one of them
| is a rule a determined student would otherwise be able to walk around by
| replaying a request:
|
|   AC-24  the deadline is the server's, not the browser's countdown
|   AC-25  the attempt limit survives a replayed start
|   AC-26  one in-progress attempt, guaranteed by a partial unique index
|
| The schema tests already prove the index exists. These prove the ACTIONS
| behave — which is the part a student interacts with.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->create();

    $this->enrollment = app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    $this->assessment = function (array $attributes = []): Assessment {
        $assessment = Assessment::factory()->create(array_merge([
            'assessable_type' => Course::class,
            'assessable_id' => $this->course->getKey(),
            'is_published' => true,
        ], $attributes));

        $question = Question::factory()->create([
            'assessment_id' => $assessment->getKey(),
            'type' => QuestionType::SingleChoice,
            'marks' => 1,
            'position' => 0,
        ]);

        QuestionOption::factory()->correct()->create(['question_id' => $question->getKey(), 'position' => 0]);
        QuestionOption::factory()->create(['question_id' => $question->getKey(), 'position' => 1]);

        return $assessment;
    };

    $this->start = fn (Assessment $a, ?User $u = null): AssessmentAttempt => app(StartAttempt::class)
        ->handle($a, $u ?? $this->student);
});

/*
| ═══════════════ STARTING ═══════════════
*/
it('starts an attempt for an enrolled student', function (): void {
    $attempt = ($this->start)(($this->assessment)());

    expect($attempt->status)->toBe(AttemptStatus::InProgress)
        ->and($attempt->attempt_number)->toBe(1)
        ->and($attempt->user_id)->toBe($this->student->getKey())
        ->and($attempt->enrollment_id)->toBe($this->enrollment->getKey());
});

it('snapshots the question order at the moment of starting', function (): void {
    $assessment = ($this->assessment)();
    $attempt = ($this->start)($assessment);

    $before = $attempt->question_order;

    // A question added later must not appear in an attempt already under way
    // (FR-ASMT-18) — the paper a student is sitting cannot change beneath them.
    Question::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'type' => QuestionType::SingleChoice,
        'position' => 5,
    ]);

    expect($attempt->refresh()->question_order)->toBe($before);
});

it('refuses an unpublished assessment', function (): void {
    expect(fn () => ($this->start)(($this->assessment)(['is_published' => false])))
        ->toThrow(AttemptNotAllowedException::class);
});

it('refuses a student with no access to the course', function (): void {
    $outsider = User::factory()->create();

    // Rule S-8: access is EnrollmentAccessService's answer, and this action
    // asks it rather than querying enrollments itself.
    expect(fn () => ($this->start)(($this->assessment)(), $outsider))
        ->toThrow(AttemptNotAllowedException::class);
});

it('refuses a student whose enrollment has been suspended mid-course', function (): void {
    $assessment = ($this->assessment)();

    ($this->start)($assessment);
    app(SubmitAttempt::class)->handle(AssessmentAttempt::query()->firstOrFail(), $this->student);

    $this->enrollment->forceFill(['status' => EnrollmentStatus::Suspended])->save();
    app(EnrollmentAccessService::class)->flush();

    expect(fn () => ($this->start)($assessment))->toThrow(AttemptNotAllowedException::class);
});

it('refuses an admin, who has access but nothing to attach an attempt to', function (): void {
    // An admin passes the access check without holding an enrollment. There
    // is no row to record an attempt against, and inventing one would put
    // staff into student statistics.
    expect(fn () => ($this->start)(($this->assessment)(), $this->admin))
        ->toThrow(AttemptNotAllowedException::class);
});

it('respects the availability window at both ends', function (?string $from, ?string $until, bool $allowed): void {
    $assessment = ($this->assessment)([
        'available_from' => $from === null ? null : now()->modify($from),
        'available_until' => $until === null ? null : now()->modify($until),
    ]);

    $start = fn () => ($this->start)($assessment);

    if ($allowed) {
        expect($start()->status)->toBe(AttemptStatus::InProgress);
    } else {
        expect($start)->toThrow(AttemptNotAllowedException::class);
    }
})->with([
    'always open' => [null, null, true],
    'opened already' => ['-1 hour', null, true],
    'not open yet' => ['+1 hour', null, false],
    'still open' => [null, '+1 hour', true],
    'closed' => [null, '-1 hour', false],
]);

/*
| ═══════════════ AC-26 — ONE ATTEMPT IN PROGRESS ═══════════════
*/
it('refuses a second attempt while one is still in progress', function (): void {
    $assessment = ($this->assessment)();
    ($this->start)($assessment);

    expect(fn () => ($this->start)($assessment))->toThrow(AttemptNotAllowedException::class);
});

it('allows a fresh attempt once the previous one is graded', function (): void {
    $assessment = ($this->assessment)();

    app(SubmitAttempt::class)->handle(($this->start)($assessment), $this->student);

    expect(($this->start)($assessment)->attempt_number)->toBe(2);
});

it('lets two different students each hold an attempt on the same assessment', function (): void {
    $assessment = ($this->assessment)();
    $other = User::factory()->create();
    app(GrantEnrollment::class)->handle($other, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    ($this->start)($assessment);

    expect(($this->start)($assessment, $other)->status)->toBe(AttemptStatus::InProgress);
});

/*
| ═══════════════ AC-25 — THE ATTEMPT LIMIT ═══════════════
*/
it('enforces the attempt limit server-side', function (): void {
    $assessment = ($this->assessment)(['max_attempts' => 2]);

    app(SubmitAttempt::class)->handle(($this->start)($assessment), $this->student);
    app(SubmitAttempt::class)->handle(($this->start)($assessment), $this->student);

    /*
     * A replayed start request is the attack this closes. The browser can be
     * made to send it as many times as anyone likes; the count is read from
     * the database each time, so the third is refused however it arrives.
     */
    expect(fn () => ($this->start)($assessment))->toThrow(AttemptNotAllowedException::class);
});

it('counts every attempt against the limit, not only the graded ones', function (): void {
    $assessment = ($this->assessment)(['max_attempts' => 1]);

    $attempt = ($this->start)($assessment);
    $attempt->forceFill(['status' => AttemptStatus::Abandoned])->save();

    // Abandoning does not buy another go. Otherwise the limit is advisory:
    // start, walk away, repeat.
    expect(fn () => ($this->start)($assessment))->toThrow(AttemptNotAllowedException::class);
});

it('allows unlimited attempts when no limit is set', function (): void {
    $assessment = ($this->assessment)(['max_attempts' => null]);

    foreach (range(1, 3) as $ignored) {
        app(SubmitAttempt::class)->handle(($this->start)($assessment), $this->student);
    }

    expect(AssessmentAttempt::query()->count())->toBe(3);
});

/*
| ═══════════════ AC-24 — THE DEADLINE IS THE SERVER'S ═══════════════
*/
it('computes the deadline from the time limit at the moment of starting', function (): void {
    $attempt = ($this->start)(($this->assessment)(['time_limit_minutes' => 30]));

    expect($attempt->expires_at)->not->toBeNull()
        ->and($attempt->started_at->diffInMinutes($attempt->expires_at))->toEqualWithDelta(30, 1);
});

it('leaves an untimed attempt with no deadline at all', function (): void {
    expect(($this->start)(($this->assessment)(['time_limit_minutes' => null]))->expires_at)->toBeNull();
});

it('refuses an answer saved after the deadline', function (): void {
    $assessment = ($this->assessment)(['time_limit_minutes' => 10]);
    $attempt = ($this->start)($assessment);
    $question = $assessment->questions()->firstOrFail();

    $this->travel(11)->minutes();

    /*
     * This is the guarantee AC-24 rests on. Because a late save is refused
     * HERE, everything in `attempt_answers` at submit time is already
     * "saved before the deadline" — grading never has to filter anything out,
     * and there is no window where a late answer counts.
     */
    expect(fn () => app(SaveAnswer::class)->handle($attempt, $question, ['selected_option_ids' => []]))
        ->toThrow(AttemptNotAllowedException::class);
});

it('marks a lapsed attempt expired the moment it is touched', function (): void {
    $assessment = ($this->assessment)(['time_limit_minutes' => 10]);
    $attempt = ($this->start)($assessment);
    $question = $assessment->questions()->firstOrFail();

    $this->travel(11)->minutes();

    try {
        app(SaveAnswer::class)->handle($attempt, $question, ['selected_option_ids' => []]);
    } catch (AttemptNotAllowedException) {
        // Expected.
    }

    // Visible immediately rather than at the next scheduled sweep: the next
    // thing this student sees should say "expired", not "in progress" for
    // another few minutes.
    expect($attempt->refresh()->status)->toBe(AttemptStatus::Expired);
});

it('grades only the answers saved before the deadline', function (): void {
    $assessment = ($this->assessment)(['time_limit_minutes' => 10, 'passing_percentage' => 50]);
    $attempt = ($this->start)($assessment);
    $question = $assessment->questions()->firstOrFail();
    $correct = $question->options()->where('is_correct', true)->firstOrFail();

    app(SaveAnswer::class)->handle($attempt, $question, ['selected_option_ids' => [$correct->getKey()]]);

    $this->travel(11)->minutes();

    $graded = app(SubmitAttempt::class)->handle($attempt->refresh(), $this->student);

    // The in-time answer still counts. Expiry closes the attempt; it does not
    // void the work already done.
    expect($graded->status)->toBe(AttemptStatus::Graded)
        ->and((float) $graded->score_percentage)->toBe(100.0);
});

it('accepts an answer inside the deadline', function (): void {
    $assessment = ($this->assessment)(['time_limit_minutes' => 30]);
    $attempt = ($this->start)($assessment);
    $question = $assessment->questions()->firstOrFail();

    $this->travel(5)->minutes();

    $answer = app(SaveAnswer::class)->handle($attempt, $question, ['answer_text' => 'x']);

    expect($answer->exists)->toBeTrue()
        ->and($answer->answer_text)->toBe('x');
});

/*
| ═══════════════ ANSWERING ═══════════════
*/
it('replaces an answer rather than appending when a student changes their mind', function (): void {
    $assessment = ($this->assessment)();
    $attempt = ($this->start)($assessment);
    $question = $assessment->questions()->firstOrFail();

    // Queried by position rather than indexed off a collection — the fixture
    // sets 0 for the correct option and 1 for the distractor.
    $first = $question->options()->where('position', 0)->firstOrFail();
    $second = $question->options()->where('position', 1)->firstOrFail();

    app(SaveAnswer::class)->handle($attempt, $question, ['selected_option_ids' => [$first->getKey()]]);
    app(SaveAnswer::class)->handle($attempt, $question, ['selected_option_ids' => [$second->getKey()]]);

    expect(AttemptAnswer::query()->where('attempt_id', $attempt->getKey())->count())->toBe(1)
        ->and(AttemptAnswer::query()->firstOrFail()->selected_option_ids)->toBe([$second->getKey()]);
});

it('refuses an answer to a question outside this attempt', function (): void {
    $attempt = ($this->start)(($this->assessment)());

    // A question id from a different assessment, supplied by hand. Nothing in
    // the UI offers it; a request can.
    $foreign = Question::factory()->create(['assessment_id' => ($this->assessment)()->getKey()]);

    expect(fn () => app(SaveAnswer::class)->handle($attempt, $foreign, ['answer_text' => 'x']))
        ->toThrow(AttemptNotAllowedException::class);
});

it('refuses an answer once the attempt is no longer in progress', function (): void {
    $assessment = ($this->assessment)();
    $attempt = ($this->start)($assessment);
    $question = $assessment->questions()->firstOrFail();

    app(SubmitAttempt::class)->handle($attempt, $this->student);

    expect(fn () => app(SaveAnswer::class)->handle($attempt->refresh(), $question, ['answer_text' => 'x']))
        ->toThrow(AttemptNotAllowedException::class);
});

/*
| ═══════════════ SUBMITTING ═══════════════
*/
it('grades on submission and records the outcome', function (): void {
    $assessment = ($this->assessment)(['passing_percentage' => 50]);
    $attempt = ($this->start)($assessment);
    $question = $assessment->questions()->firstOrFail();
    $correct = $question->options()->where('is_correct', true)->firstOrFail();

    app(SaveAnswer::class)->handle($attempt, $question, ['selected_option_ids' => [$correct->getKey()]]);

    $graded = app(SubmitAttempt::class)->handle($attempt, $this->student);

    expect($graded->status)->toBe(AttemptStatus::Graded)
        ->and($graded->is_passed)->toBeTrue()
        ->and($graded->graded_at)->not->toBeNull()
        ->and($graded->submitted_at)->not->toBeNull();
});

it('tolerates a double submission without re-grading', function (): void {
    Event::fake([AttemptGraded::class]);

    $attempt = ($this->start)(($this->assessment)());

    $first = app(SubmitAttempt::class)->handle($attempt, $this->student);
    $second = app(SubmitAttempt::class)->handle($attempt->refresh(), $this->student);

    /*
     * A double-click on a submit button, or a retry after a flaky connection,
     * on the most anxious screen in the product. Re-grading would move the
     * timestamps and fire a second result email.
     */
    expect($second->graded_at?->toIso8601String())->toBe($first->graded_at?->toIso8601String());

    Event::assertDispatchedTimes(AttemptGraded::class, 1);
});

it('announces the grading once so downstream listeners fire once', function (): void {
    Event::fake([AttemptGraded::class]);

    app(SubmitAttempt::class)->handle(($this->start)(($this->assessment)()), $this->student);

    // Progress completion and the result email both hang off this event.
    Event::assertDispatchedTimes(AttemptGraded::class, 1);
});

it('refuses to submit an attempt that was abandoned', function (): void {
    $attempt = ($this->start)(($this->assessment)());
    $attempt->forceFill(['status' => AttemptStatus::Abandoned])->save();

    expect(fn () => app(SubmitAttempt::class)->handle($attempt, $this->student))
        ->toThrow(AttemptNotAllowedException::class);
});

it('records the time spent from the server clock, not the browser', function (): void {
    $attempt = ($this->start)(($this->assessment)());

    $this->travel(7)->minutes();

    $graded = app(SubmitAttempt::class)->handle($attempt->refresh(), $this->student);

    expect($graded->time_spent_seconds)->toBeGreaterThanOrEqual(7 * 60 - 5)
        ->and($graded->time_spent_seconds)->toBeLessThanOrEqual(7 * 60 + 5);
});
