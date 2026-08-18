<?php

declare(strict_types=1);

use App\Actions\Assessment\ImportQuestions;
use App\Enums\QuestionType;
use App\Livewire\Admin\Assessments\QuestionImport;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Question;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Importing parsed questions
|--------------------------------------------------------------------------
|
| Parsing is covered in QuestionImportParsingTest. This is about the write:
|
|   nothing reaches `questions` except through CreateQuestion, so the answer-key
|   rules, sanitising, counters and audit keep applying;
|
|   a single bad row rolls the whole import back — a half-populated assessment
|   is worse than a clear refusal;
|
|   marks and type come from the author, not the file.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->course = Course::factory()->create();

    $this->assessment = Assessment::factory()->create([
        'assessable_type' => Course::class,
        'assessable_id' => $this->course->getKey(),
        'title' => 'Java Basics',
    ]);

    $this->candidate = static fn (int $row, string $body, array $options, array $overrides = []): array => array_merge([
        'row' => $row,
        'body' => $body,
        'explanation' => null,
        'options' => $options,
        'type' => QuestionType::SingleChoice,
        'marks' => 1,
    ], $overrides);

    $this->twoOptions = [
        ['body' => 'wrong', 'is_correct' => false],
        ['body' => 'right', 'is_correct' => true],
    ];
});

/*
| ═══════════════ THE WRITE ═══════════════
*/
it('creates the questions with their options', function (): void {
    $count = app(ImportQuestions::class)->handle($this->assessment, [
        ($this->candidate)(2, 'Which keyword creates a class?', $this->twoOptions),
        ($this->candidate)(3, 'Which method is the entry point?', $this->twoOptions),
    ], $this->admin);

    expect($count)->toBe(2)
        ->and($this->assessment->questions()->count())->toBe(2);

    $first = $this->assessment->questions()->orderBy('position')->firstOrFail();

    expect($first->body)->toBe('Which keyword creates a class?')
        ->and($first->options()->count())->toBe(2)
        ->and($first->options()->where('is_correct', true)->value('body'))->toBe('right');
});

it('takes marks and type from the author rather than the file', function (): void {
    app(ImportQuestions::class)->handle($this->assessment, [
        ($this->candidate)(2, 'Worth more', $this->twoOptions, ['marks' => 5]),
    ], $this->admin);

    $question = $this->assessment->questions()->firstOrFail();

    expect((float) $question->marks)->toBe(5.0)
        ->and($question->type)->toBe(QuestionType::SingleChoice);
});

it('keeps the counters in step', function (): void {
    /*
     * The counters are maintained by AssessmentCounterService inside
     * CreateQuestion. An importer that inserted rows itself would leave the
     * question count and total marks stale — the reason this action delegates.
     */
    app(ImportQuestions::class)->handle($this->assessment, [
        ($this->candidate)(2, 'One', $this->twoOptions, ['marks' => 2]),
        ($this->candidate)(3, 'Two', $this->twoOptions, ['marks' => 3]),
    ], $this->admin);

    $assessment = $this->assessment->refresh();

    expect($assessment->questions_count)->toBe(2)
        ->and((float) $assessment->total_marks)->toBe(5.0);
});

it('numbers the questions in file order', function (): void {
    app(ImportQuestions::class)->handle($this->assessment, [
        ($this->candidate)(2, 'First', $this->twoOptions),
        ($this->candidate)(3, 'Second', $this->twoOptions),
        ($this->candidate)(4, 'Third', $this->twoOptions),
    ], $this->admin);

    $bodies = $this->assessment->questions()->orderBy('position')->pluck('body')->all();

    expect($bodies)->toBe(['First', 'Second', 'Third']);
});

it('appends to an assessment that already has questions', function (): void {
    app(ImportQuestions::class)->handle($this->assessment, [
        ($this->candidate)(2, 'Existing', $this->twoOptions),
    ], $this->admin);

    app(ImportQuestions::class)->handle($this->assessment, [
        ($this->candidate)(2, 'Imported later', $this->twoOptions),
    ], $this->admin);

    /*
     * Asserted through the relation's own ordering rather than by tacking on
     * orderByDesc: `questions()` already carries `orderBy('position')`, so a
     * second order clause is appended after it and never takes effect. The
     * same trap produced the PostgreSQL grouping bug in AssessmentCounterService.
     */
    $bodies = $this->assessment->questions()->pluck('body')->all();

    expect($bodies)->toBe(['Existing', 'Imported later']);
});

/*
| ═══════════════ ALL OR NOTHING ═══════════════
*/
it('imports nothing at all when one row is rejected', function (): void {
    /*
     * A single-choice question with two correct answers fails the type's own
     * answer-key rule inside CreateQuestion (FR-ASMT-07). The rows before it
     * must not survive: "which of these 40 landed?" is a far worse question
     * than "row 3 was rejected, fix it and re-upload".
     */
    $bothCorrect = [
        ['body' => 'a', 'is_correct' => true],
        ['body' => 'b', 'is_correct' => true],
    ];

    expect(fn () => app(ImportQuestions::class)->handle($this->assessment, [
        ($this->candidate)(2, 'Fine', $this->twoOptions),
        ($this->candidate)(3, 'Two correct answers', $bothCorrect),
    ], $this->admin))->toThrow(InvalidArgumentException::class);

    expect($this->assessment->questions()->count())->toBe(0);
});

it('names the spreadsheet row that failed', function (): void {
    // Without the row number the author hunts through forty near-identical
    // questions for the one the registry objected to.
    $bothCorrect = [
        ['body' => 'a', 'is_correct' => true],
        ['body' => 'b', 'is_correct' => true],
    ];

    expect(fn () => app(ImportQuestions::class)->handle($this->assessment, [
        ($this->candidate)(7, 'Two correct answers', $bothCorrect),
    ], $this->admin))->toThrow(InvalidArgumentException::class, 'Row 7');
});

it('says plainly that nothing was imported', function (): void {
    $bothCorrect = [
        ['body' => 'a', 'is_correct' => true],
        ['body' => 'b', 'is_correct' => true],
    ];

    expect(fn () => app(ImportQuestions::class)->handle($this->assessment, [
        ($this->candidate)(2, 'Bad', $bothCorrect),
    ], $this->admin))->toThrow(InvalidArgumentException::class, 'Nothing was imported');
});

it('refuses an empty import', function (): void {
    expect(fn () => app(ImportQuestions::class)->handle($this->assessment, [], $this->admin))
        ->toThrow(InvalidArgumentException::class);
});

/*
| ═══════════════ AUDITED ═══════════════
*/
it('records the batch as one audit entry', function (): void {
    app(ImportQuestions::class)->handle($this->assessment, [
        ($this->candidate)(2, 'One', $this->twoOptions),
        ($this->candidate)(3, 'Two', $this->twoOptions),
    ], $this->admin);

    // One entry for the import, not one per question — forty rows from a
    // single upload would bury the log and say less than this one line.
    expect(AuditLog::query()->where('action', 'question.imported')->count())->toBe(1);
});

/*
| ═══════════════ THE SCREEN ═══════════════
*/
it('refuses to open for someone who cannot edit the assessment', function (): void {
    // Authorised per record by AssessmentPolicy, not by which route reached
    // the component — the same rule AssessmentBuilder follows.
    $this->actingAs(User::factory()->student()->create());

    Livewire::test(QuestionImport::class, ['assessment' => $this->assessment])
        ->call('open')
        ->assertForbidden();
});

it('imports the selected candidates and tells the list to redraw', function (): void {
    $this->actingAs($this->admin);

    $component = Livewire::test(QuestionImport::class, ['assessment' => $this->assessment])
        ->set('candidates', [
            ['row' => 2, 'body' => 'Included', 'explanation' => null, 'options' => $this->twoOptions, 'type' => 'single_choice', 'marks' => 1, 'include' => true],
            ['row' => 3, 'body' => 'Skipped', 'explanation' => null, 'options' => $this->twoOptions, 'type' => 'single_choice', 'marks' => 1, 'include' => false],
        ])
        ->call('import');

    $component->assertHasNoErrors()
        // QuestionImport is a sibling of the question list, which holds its own
        // query — without this event the author imports and sees nothing.
        ->assertDispatched('questions-imported');

    expect($this->assessment->questions()->count())->toBe(1)
        ->and($this->assessment->questions()->value('body'))->toBe('Included');
});

it('refuses when every candidate is unticked', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(QuestionImport::class, ['assessment' => $this->assessment])
        ->set('candidates', [
            ['row' => 2, 'body' => 'Skipped', 'explanation' => null, 'options' => $this->twoOptions, 'type' => 'single_choice', 'marks' => 1, 'include' => false],
        ])
        ->call('import')
        ->assertHasErrors('candidates');

    expect(Question::query()->count())->toBe(0);
});

it('rejects marks outside the allowed range', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(QuestionImport::class, ['assessment' => $this->assessment])
        ->set('candidates', [
            ['row' => 2, 'body' => 'Zero marks', 'explanation' => null, 'options' => $this->twoOptions, 'type' => 'single_choice', 'marks' => 0, 'include' => true],
        ])
        ->call('import')
        ->assertHasErrors('candidates.0.marks');

    expect(Question::query()->count())->toBe(0);
});

it('applies the batch type and marks to every candidate', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(QuestionImport::class, ['assessment' => $this->assessment])
        ->set('candidates', [
            ['row' => 2, 'body' => 'One', 'explanation' => null, 'options' => $this->twoOptions, 'type' => 'single_choice', 'marks' => 1, 'include' => true],
            ['row' => 3, 'body' => 'Two', 'explanation' => null, 'options' => $this->twoOptions, 'type' => 'single_choice', 'marks' => 1, 'include' => true],
        ])
        ->set('defaultMarks', 4)
        ->call('applyMarksToAll')
        ->assertSet('candidates.0.marks', 4)
        ->assertSet('candidates.1.marks', 4);
});

it('surfaces a rejected row on the screen without importing anything', function (): void {
    $bothCorrect = [
        ['body' => 'a', 'is_correct' => true],
        ['body' => 'b', 'is_correct' => true],
    ];

    $this->actingAs($this->admin);

    Livewire::test(QuestionImport::class, ['assessment' => $this->assessment])
        ->set('candidates', [
            ['row' => 9, 'body' => 'Two correct', 'explanation' => null, 'options' => $bothCorrect, 'type' => 'single_choice', 'marks' => 1, 'include' => true],
        ])
        ->call('import')
        ->assertHasErrors('candidates')
        ->assertNotDispatched('questions-imported');

    expect(Question::query()->count())->toBe(0);
});
