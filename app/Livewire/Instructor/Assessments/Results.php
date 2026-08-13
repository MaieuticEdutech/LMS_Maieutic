<?php

declare(strict_types=1);

namespace App\Livewire\Instructor\Assessments;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\User;
use App\Services\Assessment\AssessmentStatisticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Attempt list + cohort statistics for one assessment (FR-ASMT-17) — combines
 * what phases.md's Phase 10 frontend list names as two components
 * (Instructor\ResultsTable, Instructor\AssessmentStatistics) into one
 * screen, since a per-question correct-rate table is meaningless without
 * the attempt list it summarises and an admin/instructor views them
 * together in practice.
 *
 * SHARED BETWEEN ADMIN AND INSTRUCTOR, same reasoning and same mechanism as
 * Admin\Assessments\AssessmentBuilder — FR-ASMT-17 names both roles
 * explicitly ("Instructors and Super Admin MUST be able to view attempt
 * lists..."), and AssessmentPolicy::view() already authorises both. Layout
 * chosen dynamically in render() rather than a static #[Layout] attribute.
 */
final class Results extends Component
{
    use WithPagination;

    public Assessment $assessment;

    public function mount(Assessment $assessment): void
    {
        $this->authorize('view', $assessment);

        $this->assessment = $assessment;
    }

    /**
     * @return LengthAwarePaginator<int, AssessmentAttempt>
     */
    public function attempts(): LengthAwarePaginator
    {
        return AssessmentAttempt::query()
            ->where('assessment_id', $this->assessment->getKey())
            ->with('user:id,name')
            ->latest('started_at')
            ->paginate(15);
    }

    public function render(AssessmentStatisticsService $statistics): View
    {
        $actor = auth()->user();
        $isInstructor = $actor instanceof User && $actor->isInstructor() && ! $actor->isSuperAdmin();

        $layout = $isInstructor ? 'layouts.instructor' : 'layouts.admin';
        $indexRoute = $isInstructor ? 'instructor.assessments.index' : 'admin.assessments.index';
        $breadcrumbRoot = $isInstructor ? 'Instructor' : 'Administration';
        $breadcrumbRootUrl = $isInstructor ? '/instructor' : '/admin';

        return view('livewire.instructor.assessments.results', [
            'attempts' => $this->attempts(),
            'statistics' => $statistics->statistics($this->assessment),
            'indexRoute' => $indexRoute,
        ])->layout($layout, [
            'breadcrumbs' => [
                ['label' => $breadcrumbRoot, 'url' => $breadcrumbRootUrl],
                ['label' => 'Assessments', 'url' => route($indexRoute)],
                ['label' => 'Results', 'url' => null],
            ],
        ]);
    }
}
