<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Assessments;

use App\Actions\Assessment\ImportQuestions;
use App\Enums\QuestionType;
use App\Exceptions\SpreadsheetException;
use App\Models\Assessment;
use App\Models\User;
use App\Services\Assessment\QuestionImportParser;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Bulk question import from a spreadsheet.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THE REVIEW STEP IS THE POINT OF THIS SCREEN, NOT THE UPLOAD.
 *
 * Parsing writes nothing. Every row is shown back — question, options, which
 * option the file says is correct — and only an explicit "Import" creates
 * anything. That gate exists because the expensive failure here is silent: a
 * mis-read answer key produces questions that look right and mark students
 * wrong, and nothing downstream can detect it. A human confirming the answer
 * column is the only check that catches it.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * MARKS AND TYPE ARE CHOSEN HERE, NOT CARRIED IN THE FILE. Set them for the
 * whole batch and override any individual question — the spreadsheet holds
 * only what the question *is*, which is the part that varies between files.
 *
 * Lives under Admin\ but is used by instructors too, exactly as
 * AssessmentBuilder is: one component, one behaviour, authorised per record by
 * AssessmentPolicy rather than by which route reached it.
 */
final class QuestionImport extends Component
{
    use WithFileUploads;

    public Assessment $assessment;

    /**
     * The uploaded spreadsheet. Typed loosely because Livewire hydrates this
     * as a TemporaryUploadedFile only while a file is attached.
     */
    public mixed $file = null;

    public bool $showModal = false;

    /**
     * Parsed questions awaiting review, each with its chosen type and marks.
     *
     * Held in component state rather than a staging table: an import is a
     * single sitting, and a table would need its own lifecycle, its own
     * cleanup, and a migration for data that is meaningless once the screen
     * closes. The row cap in the parser is what keeps this payload sane.
     *
     * @var list<array<string, mixed>>
     */
    public array $candidates = [];

    /**
     * Rows the parser could not use, each naming its spreadsheet row.
     *
     * @var list<array{row: int, message: string}>
     */
    public array $problems = [];

    /** Applied to every candidate by the "set for all" controls. */
    public string $defaultType = QuestionType::SingleChoice->value;

    public int $defaultMarks = 1;

    public ?string $fileError = null;

    public function mount(Assessment $assessment): void
    {
        $this->assessment = $assessment;
    }

    public function open(): void
    {
        $this->authorize('update', $this->assessment);

        $this->reset(['file', 'candidates', 'problems', 'fileError']);
        $this->showModal = true;
    }

    public function close(): void
    {
        $this->reset(['file', 'candidates', 'problems', 'fileError', 'showModal']);
    }

    /**
     * Parse as soon as a file is attached — the author should not have to
     * press a second button to find out whether their file is readable.
     */
    public function updatedFile(QuestionImportParser $parser): void
    {
        $this->authorize('update', $this->assessment);

        $this->reset(['candidates', 'problems', 'fileError']);

        $this->validate([
            'file' => ['required', 'file', 'max:5120', 'extensions:xlsx'],
        ], attributes: ['file' => 'spreadsheet']);

        try {
            $result = $parser->parse($this->file->getRealPath());
        } catch (SpreadsheetException $e) {
            // A whole-file failure: there is nothing to review, so it is shown
            // as one message rather than as an empty table.
            $this->fileError = $e->getMessage();

            return;
        }

        $this->problems = $result['problems'];

        $this->candidates = array_map(
            fn (array $question): array => $question + [
                'type' => $this->defaultType,
                'marks' => $this->defaultMarks,
                'include' => true,
            ],
            $result['questions'],
        );
    }

    /**
     * Apply the batch type to every candidate.
     *
     * Separate from `updatedDefaultType` so changing the default does not
     * silently discard per-question overrides the author has already made —
     * they press the button when they mean it.
     */
    public function applyTypeToAll(): void
    {
        foreach (array_keys($this->candidates) as $i) {
            $this->candidates[$i]['type'] = $this->defaultType;
        }
    }

    public function applyMarksToAll(): void
    {
        foreach (array_keys($this->candidates) as $i) {
            $this->candidates[$i]['marks'] = $this->defaultMarks;
        }
    }

    public function import(ImportQuestions $import): void
    {
        $this->authorize('update', $this->assessment);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $this->validate([
            'candidates.*.marks' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'candidates.*.type' => ['required', 'string', 'in:'.implode(',', QuestionType::values())],
        ], attributes: ['candidates.*.marks' => 'marks', 'candidates.*.type' => 'question type']);

        // Unticked rows are skipped entirely — the author's way of dropping a
        // duplicate or a malformed question without editing the spreadsheet.
        $selected = array_values(array_filter(
            $this->candidates,
            static fn (array $candidate): bool => (bool) ($candidate['include'] ?? false),
        ));

        if ($selected === []) {
            $this->addError('candidates', 'No questions are selected.');

            return;
        }

        try {
            $count = $import->handle($this->assessment, array_map(
                static fn (array $candidate): array => [
                    'row' => $candidate['row'],
                    'body' => $candidate['body'],
                    'explanation' => $candidate['explanation'],
                    'options' => $candidate['options'],
                    'type' => QuestionType::from($candidate['type']),
                    'marks' => $candidate['marks'],
                ],
                $selected,
            ), $actor);
        } catch (InvalidArgumentException $e) {
            // Names the offending row and states that nothing was written, so
            // the author knows the assessment is untouched.
            $this->addError('candidates', $e->getMessage());

            return;
        }

        $this->close();

        session()->flash('status', sprintf('%d %s imported.', $count, str('question')->plural($count)));

        // The question list is a sibling component and holds its own query.
        $this->dispatch('questions-imported');
    }

    /**
     * @return list<QuestionType>
     */
    public function questionTypes(): array
    {
        return QuestionType::cases();
    }

    public function render(): View
    {
        return view('livewire.admin.assessments.question-import', [
            'questionTypes' => $this->questionTypes(),
        ]);
    }
}
