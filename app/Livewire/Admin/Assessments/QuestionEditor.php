<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Assessments;

use App\Actions\Assessment\UpdateQuestion;
use App\Models\Question;
use App\Models\User;
use App\Services\Assessment\QuestionTypeRegistry;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Per-question editor, opened as a right-side slide-over from a question row
 * in QuestionList (mirrors Admin\Courses\LessonEditor exactly). Renders the
 * question's own type-specific Blade partial via the registry — never
 * branches on QuestionType itself.
 */
final class QuestionEditor extends Component
{
    public Question $question;

    public string $body = '';

    public ?string $explanation = null;

    public string $marks = '1';

    public string $negative_marks = '0';

    /** @var list<array{body: string, is_correct: bool}> */
    public array $options = [];

    /** @var list<string> */
    public array $accepted_answers = [];

    public function mount(Question $question): void
    {
        $this->question = $question;
        $this->body = $question->body;
        $this->explanation = $question->explanation;
        $this->marks = (string) $question->marks;
        $this->negative_marks = (string) $question->negative_marks;

        $handler = app(QuestionTypeRegistry::class)->for($question->type);

        if ($handler->requiresOptions()) {
            $this->options = array_values($question->options->map(fn ($o) => [
                'body' => $o->body,
                'is_correct' => $o->is_correct,
            ])->all());
        } else {
            $this->accepted_answers = $question->meta['accepted_answers'] ?? [''];
        }
    }

    public function addOption(): void
    {
        $this->options[] = ['body' => '', 'is_correct' => false];
    }

    /**
     * Exclusive selection for single-choice/true-false: marks exactly one
     * option correct. Plain wire:model on each option's is_correct would let
     * a click leave two options marked, which FR-ASMT-07 forbids for these
     * two types.
     */
    public function markCorrectOption(int $index): void
    {
        foreach ($this->options as $i => $option) {
            $this->options[$i]['is_correct'] = $i === $index;
        }
    }

    public function removeOption(int $index): void
    {
        $remaining = array_values(array_filter(
            $this->options,
            static fn (int $i): bool => $i !== $index,
            ARRAY_FILTER_USE_KEY,
        ));

        $this->options = $remaining;
    }

    public function addAcceptedAnswer(): void
    {
        $this->accepted_answers[] = '';
    }

    public function removeAcceptedAnswer(int $index): void
    {
        $remaining = array_values(array_filter(
            $this->accepted_answers,
            static fn (int $i): bool => $i !== $index,
            ARRAY_FILTER_USE_KEY,
        ));

        $this->accepted_answers = $remaining === [] ? [''] : $remaining;
    }

    public function save(UpdateQuestion $update): void
    {
        $course = $this->question->assessment;
        $this->authorize('update', $course);

        $handler = app(QuestionTypeRegistry::class)->for($this->question->type);

        $rules = [
            'body' => ['required', 'string', 'max:2000'],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'marks' => ['required', 'numeric', 'min:0.01'],
            'negative_marks' => ['required', 'numeric', 'min:0'],
        ];

        if ($handler->requiresOptions()) {
            $rules['options'] = ['required', 'array', 'min:2'];
            $rules['options.*.body'] = ['required', 'string', 'max:500'];
            $rules['options.*.is_correct'] = ['boolean'];
        } else {
            $rules['accepted_answers'] = ['required', 'array', 'min:1'];
            $rules['accepted_answers.*'] = ['required', 'string', 'max:255'];
        }

        $validated = $this->validate($rules);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        try {
            $update->handle($this->question, [
                'body' => $validated['body'],
                'explanation' => $validated['explanation'] ?? null,
                'marks' => $validated['marks'],
                'negative_marks' => $validated['negative_marks'],
                ...$handler->requiresOptions()
                    ? ['options' => $validated['options']]
                    : ['accepted_answers' => array_values(array_filter($validated['accepted_answers'], fn ($a) => trim($a) !== ''))],
            ], $actor);
        } catch (InvalidArgumentException $e) {
            $this->addError('options', $e->getMessage());

            return;
        }

        $this->question->refresh();
        $this->dispatch('close-modal', 'question-editor-'.$this->question->id);
        session()->flash('status', 'Question saved.');
    }

    public function render(): View
    {
        $handler = app(QuestionTypeRegistry::class)->for($this->question->type);

        return view('livewire.admin.assessments.question-editor', [
            'editorView' => $handler->editorView(),
        ]);
    }
}
