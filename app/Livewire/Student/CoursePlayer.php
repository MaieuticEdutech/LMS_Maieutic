<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Actions\Student\RecordLessonProgress;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Content\ContentTypeRegistry;
use App\Services\Student\CoursePlayerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The course player (FR-STU-08, FR-STU-09, AC-02, AC-18).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * AUTHORISATION HAPPENS ON EVERY REQUEST, NOT ONCE ON MOUNT.
 *
 * A Livewire component mounts once and then handles many subsequent requests
 * against the same instance. Authorising only in mount() would mean a student
 * whose enrollment was revoked mid-session keeps working until they reload —
 * and FR-ENR-08 requires revocation to be immediate.
 *
 * So `authorise()` runs in mount AND at the top of every action. It is cheap:
 * EnrollmentAccessService memoises per request.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * The component holds only IDS in its public state, never models. Livewire
 * serialises public properties into the page, and a hydrated model would put
 * the whole row — including columns a student should never see — into the
 * browser and accept it back as input.
 */
#[Layout('layouts.app')]
final class CoursePlayer extends Component
{
    public int $courseId;

    public int $lessonId;

    public function mount(Course $course, ?Lesson $lesson = null): void
    {
        $this->authorize('access', $course);

        $this->courseId = $course->getKey();

        $player = app(CoursePlayerService::class);
        $flat = $player->flatLessons($player->curriculum($course));

        // No lesson in the URL means "resume". An unpublished or deleted
        // last_lesson_id falls back to the first lesson rather than 404 —
        // "continue learning" must never be a dead end.
        $target = $lesson?->exists === true
            ? $lesson
            : $player->resumeLesson($this->enrollment(), $flat);

        abort_if($target === null, 404, 'This course has no published lessons yet.');

        $this->assertLessonIsVisible($target, $flat);

        $this->lessonId = $target->getKey();

        // Being here is itself progress worth recording — it is what makes
        // "continue learning" point at the right place next time.
        $this->touchProgress($target);
    }

    /**
     * Record playback position, and optionally completion.
     *
     * Called by the video player's throttled reporter. Re-authorises first:
     * see the class docblock.
     */
    public function recordProgress(int $seconds, bool $completed = false): void
    {
        $this->authorize('access', $this->course());

        app(RecordLessonProgress::class)->handle(
            $this->enrollment(),
            $this->lesson(),
            positionSeconds: $seconds,
            completed: $completed ? true : null,
        );
    }

    /**
     * The manual "mark as complete" control (FR-PROG-02).
     *
     * A toggle rather than a one-way switch: a student who ticked the wrong
     * lesson must be able to correct it. Automatic completion rules arrive in
     * Phase 9.
     */
    public function toggleComplete(): void
    {
        $this->authorize('access', $this->course());

        $enrollment = $this->enrollment();
        $lesson = $this->lesson();

        $progress = app(CoursePlayerService::class)->progressMap($enrollment)[$lesson->getKey()] ?? null;

        app(RecordLessonProgress::class)->handle(
            $enrollment,
            $lesson,
            completed: $progress?->completed_at === null,
        );
    }

    public function render(CoursePlayerService $player, ContentTypeRegistry $registry): View
    {
        $course = $this->course();
        $lesson = $this->lesson();

        $curriculum = $player->curriculum($course);
        $flat = $player->flatLessons($curriculum);
        $progress = $player->progressMap($this->enrollment());

        return view('livewire.student.course-player', [
            'course' => $course,
            'lesson' => $lesson,
            'curriculum' => $curriculum,
            'progress' => $progress,
            'neighbours' => $player->neighbours($flat, $lesson),
            'completedCount' => $player->completedCount($flat, $progress),
            'totalCount' => count($flat),
            // The registry decides which partial renders this lesson, so a new
            // content type needs a handler and a view and nothing here (ADR-003).
            'playerView' => $registry->for($lesson->type)->playerView(),
            'media' => $lesson->media->first(),
            'lessonProgress' => $progress[$lesson->getKey()] ?? null,
        ]);
    }

    private function course(): Course
    {
        return Course::query()->findOrFail($this->courseId);
    }

    private function lesson(): Lesson
    {
        return Lesson::query()->with('media')->findOrFail($this->lessonId);
    }

    /**
     * The row progress is written against.
     *
     * The policy has already established access; this is how the player
     * reaches the enrollment to record against. Never used to DECIDE access —
     * that is EnrollmentAccessService's job alone (rule S-8).
     */
    private function enrollment(): Enrollment
    {
        /** @var User $student */
        $student = Auth::user();

        $enrollment = app(CoursePlayerService::class)->enrollmentFor($student, $this->course());

        // An admin or assigned instructor passes the policy without holding an
        // enrollment. They can read the course; there is no row to write
        // progress against, and inventing one would put staff into student
        // completion figures.
        abort_if($enrollment === null, 403, 'Only enrolled students can record progress.');

        return $enrollment;
    }

    /**
     * @param  list<Lesson>  $flat
     */
    private function assertLessonIsVisible(Lesson $lesson, array $flat): void
    {
        foreach ($flat as $visible) {
            if ($visible->getKey() === $lesson->getKey()) {
                return;
            }
        }

        // Reached by guessing a lesson id, or by following a link to a lesson
        // that has since been unpublished. Both are 404 rather than 403: the
        // lesson is not hidden from this student specifically, it is not part
        // of the course as published.
        abort(404, 'That lesson is not part of this course.');
    }

    private function touchProgress(Lesson $lesson): void
    {
        app(RecordLessonProgress::class)->handle($this->enrollment(), $lesson);
    }
}
