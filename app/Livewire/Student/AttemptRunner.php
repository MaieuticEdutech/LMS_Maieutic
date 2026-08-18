<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Actions\Assessment\SaveAnswer;
use App\Actions\Assessment\StartAttempt;
use App\Actions\Assessment\SubmitAttempt;
use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Exceptions\AttemptNotAllowedException;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\Assessment\QuestionPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * The attempt runner (FR-ASMT-09…FR-ASMT-11, architecture.md §10.2).
 *
 * ONE CANONICAL URL PER ASSESSMENT, mirroring CoursePlayer's own
 * resume-or-start shape: mount() finds this student's in-progress attempt
 * if one exists, or starts a new one via StartAttempt if not. There is no
 * separate "start" screen — landing here IS starting (or resuming).
 *
 * Holds only the attempt's ULID and a local answers array in public state,
 * never the full Assessment/Question models with their answer keys — same
 * reasoning as CoursePlayer's own docblock: a hydrated model in Livewire's
 * public state round-trips through the page and must never carry
 * `question_options.is_correct` or `questions.meta.accepted_answers`.
 */
#[Layout('layouts.student')]
final class AttemptRunner extends Component
{
    public string $attemptUlid;

    /**
     * Local working state, question id => selected_option_ids|answer_text.
     * Seeded from already-saved answers on mount so a reload doesn't lose
     * work (FR-ASMT-11).
     *
     * @var array<int, mixed>
     */
    public array $answers = [];

    public function mount(Assessment $assessment): void
    {
        $this->authorize('start', [AssessmentAttempt::class, $assessment]);

        /** @var User $student */
        $student = Auth::user();

        $existing = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->getKey())
            ->where('user_id', $student->getKey())
            ->where('status', AttemptStatus::InProgress)
            ->first();

        if ($existing === null) {
            try {
                $existing = app(StartAttempt::class)->handle($assessment, $student);
            } catch (AttemptNotAllowedException $e) {
                abort(403, $e->getMessage());
            }
        }

        $this->attemptUlid = $existing->ulid;
        $this->seedAnswers($existing);
    }

    /**
     * Re-authorises on every action, same reasoning as CoursePlayer: a
     * Livewire component mounts once and handles many subsequent requests,
     * so mount()-only authorisation would keep working after access should
     * have stopped.
     */
    public function saveAnswer(SaveAnswer $save, int $questionId): void
    {
        $attempt = $this->attempt();
        $this->authorize('answer', $attempt);

        $question = $this->assessmentOf($attempt)->questions()->findOrFail($questionId);

        $payload = QuestionType::from($question->type->value) === QuestionType::ShortAnswer
            ? ['answer_text' => (string) ($this->answers[$questionId] ?? '')]
            : ['selected_option_ids' => array_values(array_map('intval', (array) ($this->answers[$questionId] ?? [])))];

        try {
            $save->handle($attempt, $question, $payload);
        } catch (AttemptNotAllowedException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirect(route('student.assessments.result', $attempt->fresh()));
        }
    }

    public function submit(SubmitAttempt $submit): mixed
    {
        $attempt = $this->attempt();
        $this->authorize('submit', $attempt);

        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        // Livewire component calls all funnel through one shared update
        // endpoint, not through routes/student.php's named routes — a
        // route-level `throttle:attempt-submit` middleware would never see
        // this call. Checked directly against the same named limiter
        // AssessmentServiceProvider registers.
        $key = 'attempt-submit:user:'.$actor->getAuthIdentifier();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('action', 'Too many submit attempts. Wait a moment and try again.');

            return null;
        }

        RateLimiter::hit($key, 60);

        try {
            $graded = $submit->handle($attempt, $actor);
        } catch (AttemptNotAllowedException $e) {
            $this->addError('action', $e->getMessage());

            return null;
        }

        return redirect()->route('student.assessments.result', $graded);
    }

    public function render(QuestionPresenter $presenter): View
    {
        $attempt = $this->attempt();
        $assessment = $this->assessmentOf($attempt);

        $questionsById = $assessment->questions()->with('options')->get()->keyBy('id');

        $questions = collect($attempt->question_order)
            ->map(fn (int $id): ?Question => $questionsById->get($id))
            ->filter()
            ->map(fn (Question $q): array => $presenter->forAttempt($q))
            ->values();

        return view('livewire.student.attempt-runner', [
            'assessment' => $assessment,
            'attempt' => $attempt,
            'questions' => $questions,
        ]);
    }

    private function attempt(): AssessmentAttempt
    {
        return AssessmentAttempt::query()->where('ulid', $this->attemptUlid)->firstOrFail();
    }

    private function assessmentOf(AssessmentAttempt $attempt): Assessment
    {
        return $attempt->assessment ?? throw new RuntimeException(
            "Attempt #{$attempt->id} has no assessment — the FK constraint should make this impossible.",
        );
    }

    private function seedAnswers(AssessmentAttempt $attempt): void
    {
        foreach ($attempt->answers as $answer) {
            $this->answers[$answer->question_id] = $answer->answer_text ?? ($answer->selected_option_ids ?? []);
        }
    }
}
