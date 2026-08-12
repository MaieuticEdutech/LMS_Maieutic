<?php

declare(strict_types=1);

namespace App\Services\Content;

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
}
