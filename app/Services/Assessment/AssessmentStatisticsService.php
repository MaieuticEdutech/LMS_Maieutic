<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Enums\AttemptStatus;
use App\Models\Assessment;
use App\Models\AttemptAnswer;
use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * Cohort statistics for one assessment (FR-ASMT-17): attempts, average
 * score, pass rate, per-question correct rate. Reads only graded attempts —
 * an in-progress or expired-but-ungraded attempt has no score to average
 * in yet.
 *
 * Belongs to `App\Services\Assessment`, not `App\Services\Instructor`: the
 * numbers themselves are a property of the assessment, not of who is
 * asking. Both the admin results screen and the instructor one (scoped by
 * AssessmentPolicy before this is ever called) read the same statistics.
 */
final class AssessmentStatisticsService
{
    /**
     * @return array{
     *     attemptCount: int,
     *     averageScorePercentage: float,
     *     passRate: float,
     *     questionStats: Collection<int, array{question_id: int, body: string, correctCount: int, answeredCount: int, correctRate: float}>,
     * }
     */
    public function statistics(Assessment $assessment): array
    {
        $graded = $assessment->attempts()->where('status', AttemptStatus::Graded)->get();

        $attemptCount = $graded->count();
        $averageScorePercentage = $attemptCount > 0
            ? round((float) $graded->avg('score_percentage'), 2)
            : 0.0;
        $passRate = $attemptCount > 0
            ? round(($graded->where('is_passed', true)->count() / $attemptCount) * 100, 2)
            : 0.0;

        return [
            'attemptCount' => $attemptCount,
            'averageScorePercentage' => $averageScorePercentage,
            'passRate' => $passRate,
            'questionStats' => $this->questionStats($assessment, $graded->pluck('id')),
        ];
    }

    /**
     * @param  Collection<int, int>  $gradedAttemptIds
     * @return Collection<int, array{question_id: int, body: string, correctCount: int, answeredCount: int, correctRate: float}>
     */
    private function questionStats(Assessment $assessment, Collection $gradedAttemptIds): Collection
    {
        if ($gradedAttemptIds->isEmpty()) {
            return $assessment->questions->map(
                fn (Question $question): array => $this->questionStat($question, 0, 0, 0.0),
            );
        }

        $answers = AttemptAnswer::query()
            ->whereIn('attempt_id', $gradedAttemptIds)
            ->whereIn('question_id', $assessment->questions()->pluck('id'))
            ->get()
            ->groupBy('question_id');

        return $assessment->questions->map(function (Question $question) use ($answers): array {
            /** @var Collection<int, AttemptAnswer> $questionAnswers */
            $questionAnswers = $answers->get($question->id, collect());
            $answeredCount = $questionAnswers->count();
            $correctCount = $questionAnswers->where('is_correct', true)->count();
            $correctRate = $answeredCount > 0 ? round(($correctCount / $answeredCount) * 100, 2) : 0.0;

            return $this->questionStat($question, $correctCount, $answeredCount, $correctRate);
        });
    }

    /**
     * @return array{question_id: int, body: string, correctCount: int, answeredCount: int, correctRate: float}
     */
    private function questionStat(Question $question, int $correctCount, int $answeredCount, float $correctRate): array
    {
        return [
            'question_id' => $question->id,
            'body' => $question->body,
            'correctCount' => $correctCount,
            'answeredCount' => $answeredCount,
            'correctRate' => $correctRate,
        ];
    }
}
