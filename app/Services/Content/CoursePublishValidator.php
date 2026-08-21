<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\AssessmentType;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;

/**
 * Decides whether a course is ready to be published (FR-CRS-04, AC-17).
 *
 * ONE IMPLEMENTATION, TWO CONSUMERS (phases.md Phase 5):
 *
 *   - the Course Builder UI calls it to show a live publish checklist
 *   - PublishCourse calls it to ENFORCE the rules
 *
 * If the UI had its own copy of the rules they would drift, and the drift
 * would show up as a checklist saying "ready" next to a button that refuses.
 * The action is the authority; the checklist is the same authority rendered.
 *
 * Returns ALL blockers rather than the first, so an administrator fixes
 * everything in one pass instead of rediscovering the next problem after each
 * fix.
 */
final class CoursePublishValidator
{
    public function __construct(private readonly ContentTypeRegistry $registry) {}

    /**
     * Everything preventing this course from being published.
     * Empty means ready.
     *
     * @return list<string>
     */
    public function blockers(Course $course): array
    {
        return [
            ...$this->metadataBlockers($course),
            ...$this->structureBlockers($course),
            ...$this->finalTestBlockers($course),
        ];
    }

    public function passes(Course $course): bool
    {
        return $this->blockers($course) === [];
    }

    /**
     * @return list<string>
     */
    private function metadataBlockers(Course $course): array
    {
        $blockers = [];

        if (trim((string) $course->description) === '') {
            $blockers[] = 'The course needs a description.';
        }

        if ($course->thumbnail_path === null && $course->media()->doesntExist()) {
            $blockers[] = 'The course needs a thumbnail image.';
        }

        /*
         * ADR-014: all V1 courses are paid. The database also enforces
         * price_amount > 0, so this is belt-and-braces — but it produces a
         * readable message instead of a constraint violation.
         */
        if ($course->price_amount <= 0) {
            $blockers[] = 'The course needs a price above zero. Free courses are not supported.';
        }

        return $blockers;
    }

    /**
     * @return list<string>
     */
    private function structureBlockers(Course $course): array
    {
        $blockers = [];

        /** @var list<Module> $publishedModules */
        $publishedModules = $course->modules()
            ->where('is_published', true)
            ->with(['lessons' => fn ($q) => $q->where('is_published', true)])
            ->get()
            ->all();

        if ($publishedModules === []) {
            $blockers[] = 'The course needs at least one published module.';

            return $blockers;
        }

        $totalPublishedLessons = 0;

        foreach ($publishedModules as $module) {
            /** @var list<Lesson> $lessons */
            $lessons = $module->lessons->all();

            if ($lessons === []) {
                $blockers[] = sprintf('Module "%s" has no published lessons.', $module->title);

                continue;
            }

            $totalPublishedLessons += count($lessons);

            /*
             * Each lesson is checked by ITS OWN HANDLER — a video lesson with
             * no video, a text lesson with no body. Asking the handler rather
             * than branching on type here is what lets a new content type
             * bring its own publish rules without editing this class
             * (FR-CNT-07, P-7).
             */
            foreach ($lessons as $lesson) {
                $blockers = [
                    ...$blockers,
                    ...$this->registry->for($lesson->type)->publishBlockers($lesson),
                ];
            }
        }

        if ($totalPublishedLessons === 0) {
            $blockers[] = 'The course needs at least one published lesson.';
        }

        return $blockers;
    }

    /**
     * A course that demands a final test must have one (AC-31, FR-PROG-08).
     *
     * ═════════════════════════════════════════════════════════════════════
     * WITHOUT THIS, THE COURSE IS UNCOMPLETABLE AND NOBODY IS TOLD.
     *
     * ProgressCalculator's final-test gate is deliberately fail-safe: a course
     * that requires a test it does not have returns "not complete", so every
     * student sits at 100% of lessons and never finishes — and never earns the
     * certificate the course exists to award. That is the honest answer at
     * read time, but it surfaces days later, to the student, as a course that
     * silently refuses to end.
     *
     * Publishing is the moment to catch it, because it is the last moment the
     * author is still looking. Every other blocker in this class exists for
     * the same reason: an unreachable state is cheaper to refuse than to
     * explain.
     *
     * The test hangs off the COURSE, not a lesson — a quiz inside a module is
     * a different thing and does not satisfy this (ADR-002).
     * ═════════════════════════════════════════════════════════════════════
     *
     * @return list<string>
     */
    private function finalTestBlockers(Course $course): array
    {
        if (! $course->requires_final_test) {
            return [];
        }

        $hasFinalTest = Assessment::query()
            ->where('assessable_type', Course::class)
            ->where('assessable_id', $course->getKey())
            ->where('type', AssessmentType::Test)
            ->exists();

        if ($hasFinalTest) {
            return [];
        }

        return ['This course requires a final test, but none has been created. Add the final test, or turn the requirement off — students cannot complete the course until one exists.'];
    }
}
