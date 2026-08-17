<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The student's certificates (design handoff §7).
 *
 * Read-only, so it holds no state. Every row is scoped to the authenticated
 * user by the query itself rather than by a policy check on each result — the
 * same shape as StudentDashboardService, and for the same reason: filtering in
 * the query means there is no path by which another student's award could reach
 * this page at all, rather than a check that has to be remembered.
 *
 * The certificate's own text comes from snapshot columns, so nothing here joins
 * to `users` or `courses` to render it (see the Certificate model).
 */
#[Layout('layouts.student')]
#[Title('Certificates')]
final class Certificates extends Component
{
    public function render(): View
    {
        /** @var User $student */
        $student = Auth::user();

        return view('livewire.student.certificates', [
            'certificates' => $this->certificatesFor($student),
        ]);
    }

    /**
     * @return Collection<int, Certificate>
     */
    private function certificatesFor(User $student): Collection
    {
        return Certificate::query()
            ->where('user_id', $student->getKey())
            // The course is loaded only to link to it — never for the printed
            // title, which is the snapshot on the row.
            ->with('course')
            ->orderByDesc('issued_at')
            ->get();
    }
}
