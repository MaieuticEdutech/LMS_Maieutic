<?php

declare(strict_types=1);

namespace App\Services\Student;

use App\Models\Course;
use App\Models\Lesson;

/**
 * One item on a day of the week plan: a lesson to study, or a quiz to take.
 */
final readonly class WeekPlanBlock
{
    public function __construct(
        public Course $course,
        public Lesson $lesson,
        public string $title,
        public int $minutes,
        public bool $isQuiz,
        public bool $done,
    ) {}
}
