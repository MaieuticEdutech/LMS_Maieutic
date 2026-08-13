<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Courses;

use App\Actions\Assessment\CreateAssessment;
use App\Actions\Catalog\UpdateLesson;
use App\Enums\AssessmentType;
use App\Models\Assessment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Content\ContentTypeRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Per-lesson content editor, opened as a right-side slide-over from a lesson
 * row in LessonList (docs/UI-GUIDE.md's Course Builder reference). Renders
 * the lesson's own type-specific Blade partial via the content type
 * registry — this class never branches on LessonType itself (FR-CNT-07).
 */
final class LessonEditor extends Component
{
    public Lesson $lesson;

    public string $title = '';

    public ?string $summary = null;

    public ?string $body = null;

    public ?int $duration_seconds = null;

    public bool $is_published = false;

    public function mount(Lesson $lesson): void
    {
        $this->lesson = $lesson;
        $this->title = $lesson->title;
        $this->summary = $lesson->summary;
        $this->body = $lesson->body;
        $this->duration_seconds = $lesson->duration_seconds;
        $this->is_published = $lesson->is_published;
    }

    public function save(UpdateLesson $update): void
    {
        $course = $this->lesson->module?->course;
        abort_unless($course !== null, 404);
        $this->authorize('manageContent', $course);

        $handler = app(ContentTypeRegistry::class)->for($this->lesson->type);

        $rules = ['title' => ['required', 'string', 'max:255'], ...$handler->validationRules()];
        $validated = $this->validate($rules);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $update->handle($this->lesson, [
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'body' => $validated['body'] ?? null,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'is_published' => $this->is_published,
        ], $actor);

        $this->lesson->refresh();
        $this->dispatch('lesson-list-changed');
        $this->dispatch('close-modal', 'lesson-editor-'.$this->lesson->id);
        session()->flash('status', "Lesson \"{$this->lesson->title}\" saved.");
    }

    /**
     * The quiz lesson's attached assessment, if one exists yet — resolved
     * the same way QuizContentHandler does, kept in this class rather than
     * asked of the handler since it is presentation-only (which button to
     * show), not part of the content-type contract.
     */
    public function assessment(): ?Assessment
    {
        return Assessment::query()
            ->where('assessable_type', Lesson::class)
            ->where('assessable_id', $this->lesson->id)
            ->first();
    }

    /**
     * Creates a minimal draft assessment for this lesson and redirects into
     * the Assessment Builder — same "create is a one-line action, then
     * continue building" shape as CourseBuilder's own create flow.
     */
    public function createAssessment(CreateAssessment $create): mixed
    {
        $course = $this->lesson->module?->course;
        abort_unless($course !== null, 404);
        $this->authorize('manageContent', $course);
        $this->authorize('create', Assessment::class);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $assessment = $create->handle($this->lesson, [
            'type' => AssessmentType::Quiz,
            'title' => $this->lesson->title,
            'passing_percentage' => 70,
        ], $actor);

        return redirect()->route('admin.assessments.builder', $assessment);
    }

    public function render(): View
    {
        $handler = app(ContentTypeRegistry::class)->for($this->lesson->type);

        return view('livewire.admin.courses.lesson-editor', [
            'editorView' => $handler->editorView(),
        ]);
    }
}
