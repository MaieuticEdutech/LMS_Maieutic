<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Enums\AttemptStatus;
use App\Enums\ScoringPolicy;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\User;
use App\Services\Enrollment\EnrollmentAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Every attempt this student has made on one assessment (FR-ASMT-15) —
 * retained in full even where only the highest counts as official, so a
 * student can see their own improvement across attempts.
 */
#[Layout('layouts.student')]
final class AttemptHistory extends Component
{
    public Assessment $assessment;

    public function mount(Assessment $assessment): void
    {
        /** @var User $student */
        $student = Auth::user();
        $course = $assessment->resolveCourse();

        // Same access authority every content decision resolves through
        // (rule S-8) — a history of attempts is exactly the kind of thing an
        // unenrolled visitor to the URL must not see.
        abort_if($course === null || ! app(EnrollmentAccessService::class)->grantsAccess($student, $course), 403);

        $this->assessment = $assessment;
    }

    /**
     * @return Collection<int, AssessmentAttempt>
     */
    public function attempts(): Collection
    {
        /** @var User $student */
        $student = Auth::user();

        return AssessmentAttempt::query()
            ->where('assessment_id', $this->assessment->getKey())
            ->where('user_id', $student->getKey())
            ->orderByDesc('attempt_number')
            ->get();
    }

    /**
     * The attempt currently counted as the official score (FR-ASMT-15).
     *
     * @param  Collection<int, AssessmentAttempt>  $attempts
     */
    public function officialAttempt(Collection $attempts): ?AssessmentAttempt
    {
        $graded = $attempts->where('status', AttemptStatus::Graded);

        $official = match ($this->assessment->scoring_policy) {
            ScoringPolicy::Highest => $graded->sortByDesc('score_percentage')->first(),
            ScoringPolicy::Latest => $graded->sortByDesc('attempt_number')->first(),
            ScoringPolicy::First => $graded->sortBy('attempt_number')->first(),
        };

        return $official instanceof AssessmentAttempt ? $official : null;
    }

    public function render(): View
    {
        $attempts = $this->attempts();

        return view('livewire.student.attempt-history', [
            'attempts' => $attempts,
            'official' => $this->officialAttempt($attempts),
        ]);
    }
}
