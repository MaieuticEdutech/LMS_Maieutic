<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Enums\AttemptStatus;
use App\Models\AssessmentAttempt;
use App\Models\Question;
use App\Services\Assessment\QuestionPresenter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * The result screen (FR-ASMT-13, FR-ASMT-14, AC-27). AttemptPolicy::review()
 * covers both the owning student and admin/instructor read access — see
 * that policy's docblock for the deferred instructor-scope note.
 */
#[Layout('layouts.app')]
final class AttemptResult extends Component
{
    public AssessmentAttempt $attempt;

    public function mount(AssessmentAttempt $attempt): void
    {
        $this->authorize('review', $attempt);

        $this->attempt = $attempt;

        // Still in progress: nothing graded to show yet. Send them back to
        // finish it rather than rendering a result full of nulls. Assigned
        // above regardless — Livewire dehydrates public properties even on
        // a redirecting mount, and this one is non-nullable.
        if ($attempt->status === AttemptStatus::InProgress) {
            $this->redirect(route('student.assessments.attempt', $attempt->assessment));
        }
    }

    public function render(QuestionPresenter $presenter): View
    {
        $assessment = $this->attempt->assessment ?? throw new RuntimeException(
            "Attempt #{$this->attempt->id} has no assessment — the FK constraint should make this impossible.",
        );
        $mayReveal = $presenter->mayReveal($this->attempt);
        $questionsById = $assessment->questions()->with('options')->get()->keyBy('id');
        $answersByQuestion = $this->attempt->answers->keyBy('question_id');

        $review = collect($this->attempt->question_order)
            ->map(fn (int $id): ?Question => $questionsById->get($id))
            ->filter()
            ->map(function (Question $q) use ($presenter, $answersByQuestion, $mayReveal): array {
                $answer = $answersByQuestion->get($q->id);

                return [
                    'question' => $presenter->forReview($q, $this->attempt),
                    'answer' => $answer,
                    // The student's OWN answer correctness — distinct from
                    // the answer key above, but gated by the same policy
                    // (see attempt_answers migration's docblock).
                    'isCorrect' => $mayReveal ? $answer?->is_correct : null,
                    'marksAwarded' => $mayReveal ? $answer?->marks_awarded : null,
                ];
            })
            ->values();

        return view('livewire.student.attempt-result', [
            'assessment' => $assessment,
            'review' => $review,
            'mayReveal' => $mayReveal,
        ]);
    }
}
