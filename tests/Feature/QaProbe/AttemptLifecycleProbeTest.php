<?php

declare(strict_types=1);

use App\Actions\Assessment\SaveAnswer;
use App\Actions\Assessment\StartAttempt;
use App\Actions\Assessment\SubmitAttempt;
use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\AttemptStatus;
use App\Enums\EnrollmentSource;
use App\Enums\LessonType;
use App\Enums\QuestionType;
use App\Enums\ScoringPolicy;
use App\Exceptions\AttemptNotAllowedException;
use App\Livewire\Student\AttemptHistory;
use App\Livewire\Student\AttemptRunner;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| QA PROBE — attempt lifecycle, grading and scoring policy
|--------------------------------------------------------------------------
|
| Plan IDs: S-16, S-17, S-18, S-19, S-21, S-22, S-23, S-24, S-25, S-28, S-29.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();

    $this->course = Course::factory()->published()->create();
    $module = Module::factory()->forCourse($this->course)->published()->create();
    $this->lesson = Lesson::factory()->forModule($module)->published()->atPosition(0)
        ->create(['type' => LessonType::Quiz]);

    /** Two single-choice questions, 1 mark each. */
    $this->buildAssessment = function (array $overrides = []): Assessment {
        $assessment = Assessment::factory()->create(array_merge([
            'assessable_type' => Lesson::class,
            'assessable_id' => $this->lesson->getKey(),
            'passing_percentage' => 50,
            'is_published' => true,
        ], $overrides));

        foreach ([1, 2] as $n) {
            $q = Question::factory()->create([
                'assessment_id' => $assessment->getKey(),
                'type' => QuestionType::SingleChoice,
                'body' => "Question {$n}",
                'marks' => 1,
            ]);
            QuestionOption::factory()->correct()->create(['question_id' => $q->getKey(), 'body' => "right{$n}"]);
            QuestionOption::factory()->create(['question_id' => $q->getKey(), 'body' => "wrong{$n}"]);
        }

        return $assessment->refresh();
    };

    $this->enrol = fn (User $u) => app(GrantEnrollment::class)
        ->handle($u, $this->course, EnrollmentSource::AdminGrant, $this->admin);

    /** Answer every question correctly or incorrectly, then submit. */
    $this->attemptWith = function (Assessment $assessment, bool $correct): AssessmentAttempt {
        $attempt = app(StartAttempt::class)->handle($assessment, $this->student);

        foreach ($assessment->questions()->with('options')->get() as $question) {
            $option = $question->options->firstWhere('is_correct', $correct);

            if (! $option instanceof QuestionOption) {
                throw new RuntimeException("Question {$question->getKey()} has no option to select.");
            }

            app(SaveAnswer::class)->handle($attempt, $question, [
                'selected_option_ids' => [$option->getKey()],
            ]);
        }

        return app(SubmitAttempt::class)->handle($attempt->refresh(), $this->student);
    };

    $this->actingAs($this->student);
});

/*
| ═══════════ S-18 / S-19 — one in-progress attempt, enforced ═══════════
*/

it('resumes the same attempt instead of starting a second one', function (): void {
    $assessment = ($this->buildAssessment)();
    ($this->enrol)($this->student);

    $first = Livewire::test(AttemptRunner::class, ['assessment' => $assessment]);
    $second = Livewire::test(AttemptRunner::class, ['assessment' => $assessment]);

    expect($second->get('attemptUlid'))->toBe($first->get('attemptUlid'))
        ->and(AssessmentAttempt::query()->where('assessment_id', $assessment->getKey())->count())->toBe(1);
});

it('refuses a second in-progress attempt at the database level', function (): void {
    $assessment = ($this->buildAssessment)();
    ($this->enrol)($this->student);

    app(StartAttempt::class)->handle($assessment, $this->student);

    // Bypass the Action entirely — the partial unique index is the guarantee.
    expect(fn () => AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'user_id' => $this->student->getKey(),
        'status' => AttemptStatus::InProgress,
        'attempt_number' => 99,
    ]))->toThrow(QueryException::class);
});

/*
| ═══════════ S-21 / S-22 — attempt ceilings ═══════════
*/

it('refuses an attempt past max_attempts', function (): void {
    $assessment = ($this->buildAssessment)(['max_attempts' => 2]);
    ($this->enrol)($this->student);

    ($this->attemptWith)($assessment, true);
    ($this->attemptWith)($assessment, true);

    expect(fn () => app(StartAttempt::class)->handle($assessment, $this->student))
        ->toThrow(AttemptNotAllowedException::class);
});

it('allows unlimited attempts when max_attempts is null', function (): void {
    $assessment = ($this->buildAssessment)(['max_attempts' => null]);
    ($this->enrol)($this->student);

    foreach (range(1, 4) as $ignored) {
        ($this->attemptWith)($assessment, true);
    }

    expect(AssessmentAttempt::query()->where('assessment_id', $assessment->getKey())->count())->toBe(4);
});

/*
| ═══════════ S-17 / S-29 — grading arithmetic ═══════════
*/

it('grades a fully correct attempt at one hundred percent and passes it', function (): void {
    $assessment = ($this->buildAssessment)();
    ($this->enrol)($this->student);

    $attempt = ($this->attemptWith)($assessment, true);

    expect((float) $attempt->score_percentage)->toBe(100.0)
        ->and($attempt->is_passed)->toBeTrue()
        ->and($attempt->status)->toBe(AttemptStatus::Graded);
});

it('grades a fully wrong attempt at zero and fails it', function (): void {
    $assessment = ($this->buildAssessment)();
    ($this->enrol)($this->student);

    $attempt = ($this->attemptWith)($assessment, false);

    expect((float) $attempt->score_percentage)->toBe(0.0)
        ->and($attempt->is_passed)->toBeFalse();
});

it('scores an unanswered submission at zero without erroring', function (): void {
    $assessment = ($this->buildAssessment)();
    ($this->enrol)($this->student);

    $attempt = app(StartAttempt::class)->handle($assessment, $this->student);
    $graded = app(SubmitAttempt::class)->handle($attempt, $this->student);

    expect((float) $graded->score_percentage)->toBe(0.0)
        ->and($graded->is_passed)->toBeFalse();
});

/*
| ═══════════ S-23 / S-24 / S-25 — scoring policy selects the official attempt
|
| NOTE: this rule lives in AttemptHistory (a Livewire component), so these
| probes must drive the component. That placement is itself a finding.
*/

it('selects the highest attempt as official under the highest policy', function (): void {
    $assessment = ($this->buildAssessment)(['scoring_policy' => ScoringPolicy::Highest]);
    ($this->enrol)($this->student);

    ($this->attemptWith)($assessment, false); // 0%
    ($this->attemptWith)($assessment, true);  // 100%
    ($this->attemptWith)($assessment, false); // 0%

    $component = Livewire::test(AttemptHistory::class, ['assessment' => $assessment]);
    $official = $component->viewData('official');

    expect((float) $official->score_percentage)->toBe(100.0);
});

it('selects the most recent attempt as official under the latest policy', function (): void {
    $assessment = ($this->buildAssessment)(['scoring_policy' => ScoringPolicy::Latest]);
    ($this->enrol)($this->student);

    ($this->attemptWith)($assessment, true);  // 100%, attempt 1
    ($this->attemptWith)($assessment, false); // 0%, attempt 2

    $component = Livewire::test(AttemptHistory::class, ['assessment' => $assessment]);
    $official = $component->viewData('official');

    expect((float) $official->score_percentage)->toBe(0.0)
        ->and($official->attempt_number)->toBe(2);
});

it('selects the first attempt as official under the first policy', function (): void {
    $assessment = ($this->buildAssessment)(['scoring_policy' => ScoringPolicy::First]);
    ($this->enrol)($this->student);

    ($this->attemptWith)($assessment, false); // 0%, attempt 1
    ($this->attemptWith)($assessment, true);  // 100%, attempt 2

    $component = Livewire::test(AttemptHistory::class, ['assessment' => $assessment]);
    $official = $component->viewData('official');

    expect($official->attempt_number)->toBe(1)
        ->and((float) $official->score_percentage)->toBe(0.0);
});

/*
| ═══════════ S-07 / access — an unenrolled student cannot start ═══════════
*/

it('refuses to start an attempt for an unenrolled student', function (): void {
    $assessment = ($this->buildAssessment)();

    expect(fn () => app(StartAttempt::class)->handle($assessment, $this->student))
        ->toThrow(AttemptNotAllowedException::class);
});

it('refuses to start an attempt on an unpublished assessment', function (): void {
    $assessment = ($this->buildAssessment)(['is_published' => false]);
    ($this->enrol)($this->student);

    expect(fn () => app(StartAttempt::class)->handle($assessment, $this->student))
        ->toThrow(AttemptNotAllowedException::class);
});
