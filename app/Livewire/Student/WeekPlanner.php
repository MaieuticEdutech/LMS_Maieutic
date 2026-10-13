<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Actions\Student\SetWeeklyGoal;
use App\Models\User;
use App\Services\Student\WeekPlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * "Your week, planned": a seven-day calendar of what the student finished and
 * what to study next, with a weekly goal they can change.
 *
 * Embed with `<livewire:student.week-planner />`. Everything shown is computed
 * by WeekPlanService for the authenticated user, and the goal is written only
 * through SetWeeklyGoal.
 */
final class WeekPlanner extends Component
{
    /** The "custom" goal field, in hours. */
    public string $customHours = '';

    public function setGoal(int $minutes, SetWeeklyGoal $action): void
    {
        $this->applyGoal($minutes, $action);
    }

    public function setCustomGoal(SetWeeklyGoal $action): void
    {
        $this->resetErrorBag();

        $hours = str_replace(',', '.', trim($this->customHours));

        if ($hours === '' || ! is_numeric($hours)) {
            $this->addError('customHours', 'Enter the hours per week, for example 6.');

            return;
        }

        $this->applyGoal((int) round(((float) $hours) * 60), $action);
    }

    public function render(WeekPlanService $weekPlan): View
    {
        /** @var User $student */
        $student = Auth::user();

        return view('livewire.student.week-planner', [
            'plan' => $weekPlan->forStudent($student),
            'minGoal' => SetWeeklyGoal::MIN_MINUTES,
            'maxGoal' => SetWeeklyGoal::MAX_MINUTES,
        ]);
    }

    private function applyGoal(int $minutes, SetWeeklyGoal $action): void
    {
        $this->resetErrorBag();

        /** @var User $student */
        $student = Auth::user();

        try {
            $action->handle($student, $minutes, $student);
        } catch (ValidationException $e) {
            $this->addError('customHours', (string) ($e->validator->errors()->first() ?: 'Invalid goal.'));

            return;
        }

        $this->customHours = '';
    }
}
