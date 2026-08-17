<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Assessments;

use App\Actions\Assessment\CreateQuestion;
use App\Actions\Assessment\DeleteQuestion;
use App\Actions\Assessment\ReorderQuestions;
use App\Enums\QuestionType;
use App\Exceptions\AssessmentDeletionException;
use App\Exceptions\ReorderException;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\User;
use App\Services\Assessment\QuestionTypeRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

/**
 * The question list inside AssessmentBuilder's structure panel — owns
 * question create/delete and reorder; editing a question's content opens
 * QuestionEditor. Mirrors Admin\Courses\ModuleList exactly.
 */
final class QuestionList extends Component
{
    public Assessment $assessment;

    public bool $showForm = false;

    public string $type = '';

    public ?int $deletingQuestionId = null;

    public function mount(Assessment $assessment): void
    {
        $this->assessment = $assessment;
    }

    public function openCreate(): void
    {
        $this->authorize('update', $this->assessment);

        $this->reset(['type']);
        $this->showForm = true;
    }

    public function save(CreateQuestion $create): void
    {
        $this->authorize('update', $this->assessment);

        $validated = $this->validate([
            'type' => ['required', 'string', 'in:'.implode(',', QuestionType::values())],
        ]);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $type = QuestionType::from($validated['type']);

        // A blank starting question — the author fills in body, marks and
        // the answer key immediately afterward in QuestionEditor. Choice
        // types get two blank options (or, for true/false, the fixed pair)
        // so the editor always has something to edit rather than an empty
        // list with no "add option" affordance yet built.
        $create->handle($this->assessment, [
            'type' => $type,
            'body' => 'New question',
            'marks' => 1,
            'options' => match ($type) {
                QuestionType::TrueFalse => [
                    ['body' => 'True', 'is_correct' => true],
                    ['body' => 'False', 'is_correct' => false],
                ],
                QuestionType::SingleChoice, QuestionType::MultipleChoice => [
                    ['body' => 'Option 1', 'is_correct' => true],
                    ['body' => 'Option 2', 'is_correct' => false],
                ],
                QuestionType::ShortAnswer => [],
            },
            'accepted_answers' => $type === QuestionType::ShortAnswer ? ['answer'] : [],
        ], $actor);

        $this->assessment->refresh();
        $this->showForm = false;
        $this->reset(['type']);
    }

    public function confirmDelete(int $questionId): void
    {
        $this->deletingQuestionId = $questionId;
    }

    public function delete(DeleteQuestion $delete): void
    {
        if ($this->deletingQuestionId === null) {
            return;
        }

        $this->authorize('update', $this->assessment);

        $question = $this->assessment->questions()->findOrFail($this->deletingQuestionId);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        try {
            $delete->handle($question, $actor);
        } catch (AssessmentDeletionException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->assessment->refresh();
        $this->deletingQuestionId = null;
    }

    public function moveQuestion(int $questionId, int $direction): void
    {
        $ids = array_values($this->assessment->questions()->pluck('id')->map(fn ($id) => (int) $id)->all());
        $index = array_search($questionId, $ids, true);

        if ($index === false) {
            return;
        }

        $index = (int) $index;
        $target = $index + $direction;

        if ($target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        $this->reorder(array_values($ids));
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        $this->authorize('update', $this->assessment);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        try {
            app(ReorderQuestions::class)->handle($this->assessment, $orderedIds, $actor);
        } catch (ReorderException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->assessment->refresh();
    }

    /**
     * @return Collection<int, Question>
     */
    public function questions(): Collection
    {
        return $this->assessment->questions()->with('options')->get();
    }

    /**
     * @return array<string, QuestionType>
     */
    public function questionTypes(): array
    {
        return array_map(
            static fn ($handler) => $handler->type(),
            app(QuestionTypeRegistry::class)->all(),
        );
    }

    public function render(): View
    {
        return view('livewire.admin.assessments.question-list', [
            'questions' => $this->questions(),
            /*
             * questionTypes() existed and was never passed to the view, which
             * reads it as `$questionTypes`. The markup that uses it sits
             * inside `@if ($showForm)` — false on first paint — so the page
             * rendered fine and the 500 arrived only when somebody clicked
             * "Add question". Dead until the branch is taken, then broken.
             *
             * The list comes from QuestionTypeRegistry rather than from the
             * enum, so a type registered without a handler cannot be offered
             * to an author (ADR-003's rule, applied to questions).
             */
            'questionTypes' => $this->questionTypes(),
        ]);
    }
}
