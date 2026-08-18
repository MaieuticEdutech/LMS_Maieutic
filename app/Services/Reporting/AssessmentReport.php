<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\AttemptStatus;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Per assessment: attempts, average score, pass rate — and per question, the
 * correct rate (FR-RPT-04).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THE PER-QUESTION CORRECT RATE IS THE COLUMN THAT EARNS THIS REPORT.
 *
 * If ninety percent of a cohort gets question 7 wrong, the overwhelmingly
 * likely explanation is that question 7 is badly worded or its answer key is
 * wrong — not that ninety percent failed to learn that topic. There is no
 * other way to find a broken item: individually, every student just sees a
 * mark they believe they earned, and nobody reports it.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * GRADED ATTEMPTS ONLY. An in-progress or abandoned attempt has no score, and
 * counting it as zero would drag every average down and make a popular
 * assessment look like a failing one.
 */
final class AssessmentReport
{
    public function __construct(private readonly ReportScope $scope) {}

    /**
     * @return Collection<int, array{id: int, assessment: string, course: string, attempts: int, average: float, pass_rate: float}>
     */
    public function perAssessment(User $actor, DateRange $range): Collection
    {
        $courseIds = $this->scope->courseIds($actor);

        $rows = [];

        foreach (Assessment::query()->with('assessable')->get() as $assessment) {
            $course = $assessment->resolveCourse();

            // Deny-safe: an assessment whose course cannot be resolved is
            // omitted rather than reported unscoped.
            if ($course === null) {
                continue;
            }

            if ($courseIds !== null && ! in_array($course->getKey(), $courseIds, true)) {
                continue;
            }

            $row = $this->row($assessment, $course->title, $range);

            // An assessment nobody has sat has nothing to report; a table of
            // zeroes buries the ones that do.
            if ($row['attempts'] > 0) {
                $rows[] = $row;
            }
        }

        usort($rows, static fn (array $a, array $b): int => $b['attempts'] <=> $a['attempts']);

        return new Collection($rows);
    }

    /**
     * @return array{id: int, assessment: string, course: string, attempts: int, average: float, pass_rate: float}
     */
    private function row(Assessment $assessment, string $courseTitle, DateRange $range): array
    {
        $query = $this->gradedAttempts($assessment, $range)
            ->selectRaw('COUNT(*) AS attempts')
            ->selectRaw('COALESCE(AVG(score_percentage), 0) AS average')
            ->selectRaw('COUNT(*) FILTER (WHERE is_passed) AS passed');

        /** @var object{attempts: int|string, average: float|string, passed: int|string}|null $stats */
        $stats = $query->first();

        $attempts = (int) ($stats->attempts ?? 0);
        $passed = (int) ($stats->passed ?? 0);

        return [
            // Carried so the screen can expand a row into its per-question
            // breakdown. Dropped from the CSV, which is read by a person.
            'id' => (int) $assessment->getKey(),
            'assessment' => $assessment->title,
            'course' => $courseTitle,
            'attempts' => $attempts,
            'average' => round((float) ($stats->average ?? 0), 1),
            'pass_rate' => $attempts > 0 ? round($passed / $attempts * 100, 1) : 0.0,
        ];
    }

    /**
     * Correct rate for every question on one assessment, weakest first — the
     * order a person reviewing their own questions actually wants.
     *
     * @return Collection<int, array{question: string, answered: int, correct: int, correct_rate: float}>
     */
    public function perQuestion(Assessment $assessment, DateRange $range): Collection
    {
        $attemptIds = $this->gradedAttempts($assessment, $range)->pluck('id');

        return Question::query()
            ->where('assessment_id', $assessment->getKey())
            ->orderBy('position')
            ->get()
            ->map(function (Question $question) use ($attemptIds): array {
                $answers = $question->answers()
                    ->whereIn('attempt_id', $attemptIds)
                    ->selectRaw('COUNT(*) AS answered')
                    ->selectRaw('COUNT(*) FILTER (WHERE is_correct) AS correct')
                    ->first();

                $answered = (int) ($answers->answered ?? 0);
                $correct = (int) ($answers->correct ?? 0);

                return [
                    'question' => \Illuminate\Support\Str::limit($question->body, 80),
                    'answered' => $answered,
                    'correct' => $correct,
                    'correct_rate' => $answered > 0 ? round($correct / $answered * 100, 1) : 0.0,
                ];
            })
            ->sortBy('correct_rate')
            ->values();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<AssessmentAttempt>
     */
    private function gradedAttempts(Assessment $assessment, DateRange $range): \Illuminate\Database\Eloquent\Builder
    {
        $query = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->getKey())
            ->where('status', AttemptStatus::Graded);

        // On submitted_at: when the student finished, not when the row was
        // created, which for a resumed attempt can be days earlier.
        if ($range->from !== null) {
            $query->where('submitted_at', '>=', $range->from);
        }

        if ($range->to !== null) {
            $query->where('submitted_at', '<=', $range->to);
        }

        return $query;
    }
}
