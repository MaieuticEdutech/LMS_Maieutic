<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Assessments;

use App\Actions\Assessment\DeleteAssessment;
use App\Actions\Assessment\PublishAssessment;
use App\Actions\Assessment\UnpublishAssessment;
use App\Actions\Assessment\UpdateAssessment;
use App\Enums\AnswerRevealPolicy;
use App\Enums\ScoringPolicy;
use App\Exceptions\AssessmentDeletionException;
use App\Exceptions\AssessmentPublishException;
use App\Models\Assessment;
use App\Models\User;
use App\Services\Assessment\AssessmentPublishValidator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The assessment authoring screen (phases.md Phase 8, architecture.md §10.4)
 * — meta form + question list, same "one combined screen" shape as
 * Admin\Courses\CourseBuilder. Always edits an existing, already-persisted
 * Assessment: creation itself is a one-line action from the attach point
 * (LessonEditor's "Create assessment" button, CourseBuilder's final-test
 * section) that redirects straight here, mirroring how CourseBuilder's own
 * "create" is really "save a minimal draft, then continue building".
 *
 * SHARED BETWEEN ADMIN AND INSTRUCTOR (Phase 10) — one implementation
 * rather than a near-duplicate Instructor\Assessments\AssessmentBuilder,
 * since the two would drift the moment one changed and not the other. Every
 * mutating method already authorises through AssessmentPolicy, which now
 * allows an assigned instructor exactly the same as it allows a super
 * admin — this class does not need to know which one is looking at it,
 * except to pick the right chrome (layout, breadcrumbs, "back" link),
 * decided once in render() via Livewire's imperative `->layout()` rather
 * than the static `#[Layout]` attribute, which cannot vary per request.
 */
final class AssessmentBuilder extends Component
{
    public Assessment $assessment;

    public string $title = '';

    public ?string $instructions = null;

    public int $passing_percentage = 70;

    public ?int $time_limit_minutes = null;

    public ?int $max_attempts = null;

    public string $scoring_policy = 'highest';

    public bool $shuffle_questions = false;

    public bool $shuffle_options = false;

    public string $answer_reveal = 'after_submit';

    public bool $negative_marking_enabled = false;

    public function mount(Assessment $assessment): void
    {
        $this->authorize('update', $assessment);

        $this->assessment = $assessment;
        $this->title = $assessment->title;
        $this->instructions = $assessment->instructions;
        $this->passing_percentage = $assessment->passing_percentage;
        $this->time_limit_minutes = $assessment->time_limit_minutes;
        $this->max_attempts = $assessment->max_attempts;
        $this->scoring_policy = $assessment->scoring_policy->value;
        $this->shuffle_questions = $assessment->shuffle_questions;
        $this->shuffle_options = $assessment->shuffle_options;
        $this->answer_reveal = $assessment->answer_reveal->value;
        $this->negative_marking_enabled = $assessment->negative_marking_enabled;
    }

    public function save(UpdateAssessment $update): void
    {
        $this->authorize('update', $this->assessment);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'passing_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'max_attempts' => ['nullable', 'integer', 'min:1'],
            'scoring_policy' => ['required', 'string', 'in:'.implode(',', ScoringPolicy::values())],
            'shuffle_questions' => ['boolean'],
            'shuffle_options' => ['boolean'],
            'answer_reveal' => ['required', 'string', 'in:'.implode(',', AnswerRevealPolicy::values())],
            'negative_marking_enabled' => ['boolean'],
        ]);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $update->handle($this->assessment, [
            ...$validated,
            'scoring_policy' => ScoringPolicy::from($validated['scoring_policy']),
            'answer_reveal' => AnswerRevealPolicy::from($validated['answer_reveal']),
        ], $actor);

        $this->assessment->refresh();

        session()->flash('status', "Assessment \"{$this->assessment->title}\" saved.");
    }

    public function publish(PublishAssessment $publish): void
    {
        $this->authorize('publish', $this->assessment);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        try {
            $publish->handle($this->assessment, $actor);
            $this->assessment->refresh();
            session()->flash('status', "Assessment \"{$this->assessment->title}\" published.");
        } catch (AssessmentPublishException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function unpublish(UnpublishAssessment $unpublish): void
    {
        $this->authorize('publish', $this->assessment);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $unpublish->handle($this->assessment, $actor);
        $this->assessment->refresh();
        session()->flash('status', "Assessment \"{$this->assessment->title}\" unpublished.");
    }

    public function delete(DeleteAssessment $delete): mixed
    {
        $this->authorize('delete', $this->assessment);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        try {
            $delete->handle($this->assessment, $actor);

            return redirect()->route($this->indexRouteName());
        } catch (AssessmentDeletionException $e) {
            session()->flash('error', $e->getMessage());

            return null;
        }
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function publishBlockers(): array
    {
        return app(AssessmentPublishValidator::class)->blockers($this->assessment);
    }

    public function render(): View
    {
        $actor = auth()->user();
        $isInstructor = $actor instanceof User && $actor->isInstructor() && ! $actor->isSuperAdmin();

        $layout = $isInstructor ? 'layouts.instructor' : 'layouts.admin';
        $breadcrumbRoot = $isInstructor ? 'Instructor' : 'Administration';
        $breadcrumbRootUrl = $isInstructor ? '/instructor' : '/admin';

        return view('livewire.admin.assessments.builder', [
            'indexRoute' => $this->indexRouteName(),
        ])->layout($layout, [
            'breadcrumbs' => [
                ['label' => $breadcrumbRoot, 'url' => $breadcrumbRootUrl],
                ['label' => 'Assessments', 'url' => route($this->indexRouteName())],
                ['label' => 'Builder', 'url' => null],
            ],
        ]);
    }

    /**
     * Which index page "back" points to and delete() redirects to — the
     * only place this class distinguishes its two audiences beyond chrome.
     */
    private function indexRouteName(): string
    {
        $actor = auth()->user();

        return ($actor instanceof User && $actor->isInstructor() && ! $actor->isSuperAdmin())
            ? 'instructor.assessments.index'
            : 'admin.assessments.index';
    }
}
