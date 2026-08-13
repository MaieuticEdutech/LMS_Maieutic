<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Enrollments;

use App\Actions\Enrollment\ReinstateEnrollment;
use App\Actions\Enrollment\RevokeEnrollment;
use App\Actions\Enrollment\SuspendEnrollment;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Livewire\Concerns\WithAdminTable;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin enrolments list, with the three lifecycle controls (phases.md Phase 6,
 * FR-ENR-07, FR-ENR-08).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THIS COMPONENT MUTATES NOTHING ITSELF.
 *
 * Suspend, reinstate and revoke each delegate to Track A's single-owner
 * actions, which own the state machine, the audit entry and the access-cache
 * flush. This class validates input, authorises, calls one action and reports
 * the result (Rule 16: no business logic in a Livewire component).
 *
 * That split is why a UI mistake here cannot become a security or data
 * problem: `RevokeEnrollment` refuses an empty reason and `SuspendEnrollment`
 * refuses a terminal status whatever this screen sends them.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * REVOCATION REQUIRES TYPED CONFIRMATION (FR-ADM-17). The administrator types
 * REVOKE, not merely clicks a red button. Revocation removes paid access and
 * is not undoable from this screen — restoring it is a fresh grant — so the
 * confirmation step is proportionate rather than ceremonial.
 */
#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Enrolments', 'url' => null],
    ],
])]
final class EnrollmentsTable extends Component
{
    use WithAdminTable;
    use WithPagination;

    public string $statusFilter = '';

    public string $sourceFilter = '';

    public string $courseFilter = '';

    /** The enrolment the open modal is acting on. */
    public ?int $actingOnId = null;

    public string $reason = '';

    /** FR-ADM-17: must equal CONFIRM_WORD before a revoke is accepted. */
    public string $confirmation = '';

    /**
     * Whether this revocation follows a refund.
     *
     * Exposed rather than hardcoded because `RevokeEnrollment` stores a
     * different status for each — `refunded` vs `expired` — specifically "so
     * the commercial history stays legible". Only the administrator knows
     * which happened, so only they can answer it; defaulting silently would
     * make Phase 13's revenue reporting quietly wrong.
     */
    public bool $refunded = false;

    /**
     * The word an administrator types to confirm a revocation.
     *
     * Deliberately not the student's name or the course title: those can be
     * copied from the row above without reading it, which defeats the point.
     * An unambiguous verb makes the consequence explicit.
     */
    public const CONFIRM_WORD = 'REVOKE';

    public function mount(): void
    {
        $this->authorize('viewAny', Enrollment::class);
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSourceFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCourseFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Enrollment>
     */
    public function rows(): LengthAwarePaginator
    {
        /*
         * Eager-loaded because the table renders both on every row, and
         * Model::preventLazyLoading() turns an N+1 here into a hard failure
         * rather than a slow page (NFR-PERF-03, AC-28).
         */
        $query = Enrollment::query()->with(['user:id,name,email', 'course:id,title']);

        /*
         * Search spans the RELATED student and course, not the enrolment row,
         * which has nothing human-readable on it. whereHas keeps this a single
         * query rather than filtering a loaded collection in PHP.
         */
        if ($this->search !== '') {
            $term = '%'.$this->search.'%';

            $query->where(function (Builder $query) use ($term): void {
                $query->whereHas('user', function (Builder $user) use ($term): void {
                    $user->where('name', 'like', $term)->orWhere('email', 'like', $term);
                })->orWhereHas('course', function (Builder $course) use ($term): void {
                    $course->where('title', 'like', $term);
                });
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->sourceFilter !== '') {
            $query->where('source', $this->sourceFilter);
        }

        if ($this->courseFilter !== '') {
            $query->where('course_id', $this->courseFilter);
        }

        return $this->applySort($query, default: 'enrolled_at', defaultDirection: 'desc')
            ->paginate($this->perPage);
    }

    /**
     * Headline counts for the page subtitle.
     *
     * Counted with the database rather than by walking the paginator: the
     * figure describes every enrolment, not the fifteen on screen.
     *
     * @return array{total: int, active: int}
     */
    public function summary(): array
    {
        return [
            'total' => Enrollment::query()->count(),
            'active' => Enrollment::query()->where('status', EnrollmentStatus::Active)->count(),
        ];
    }

    /**
     * Courses that actually have an enrolment, for the filter.
     *
     * A filter listing every course in the catalogue would be mostly dead
     * options that return nothing.
     *
     * @return array<int, string>
     */
    public function courseOptions(): array
    {
        return Course::query()
            ->whereHas('enrollments')
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    public function actingOn(): ?Enrollment
    {
        if ($this->actingOnId === null) {
            return null;
        }

        return Enrollment::query()->with(['user:id,name,email', 'course:id,title'])->find($this->actingOnId);
    }

    /*
    |----------------------------------------------------------------------
    | Lifecycle controls
    |----------------------------------------------------------------------
    */

    public function confirmRevoke(int $enrollmentId): void
    {
        $this->prepareModal($enrollmentId, 'revoke-enrollment');
    }

    public function confirmSuspend(int $enrollmentId): void
    {
        $this->prepareModal($enrollmentId, 'suspend-enrollment');
    }

    public function revoke(RevokeEnrollment $revokeEnrollment): void
    {
        $enrollment = $this->authorisedTarget('revoke');

        /*
         * FR-ADM-17. `in:REVOKE` rather than `same:` against another property:
         * `same` compares two pieces of SUBMITTED state, and the expected word
         * is a constant, not an input. Comparing an input against another
         * input the user also controls would confirm nothing.
         */
        $this->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'confirmation' => ['required', 'string', 'in:'.self::CONFIRM_WORD],
        ], [
            'reason.required' => 'A reason is required. It is recorded against the student\'s record and shown to whoever reviews this later.',
            'confirmation.required' => 'Type '.self::CONFIRM_WORD.' to confirm.',
            'confirmation.in' => 'Type '.self::CONFIRM_WORD.' exactly, in capitals, to confirm.',
        ]);

        $this->runAction(
            fn (User $actor) => $revokeEnrollment->handle(
                enrollment: $enrollment,
                actor: $actor,
                reason: trim($this->reason),
                refunded: $this->refunded,
            ),
            sprintf(
                'Access revoked for %s%s. They have been emailed.',
                $this->studentName($enrollment),
                $this->refunded ? ', and recorded as refunded' : '',
            ),
            'revoke-enrollment',
        );
    }

    public function suspend(SuspendEnrollment $suspendEnrollment): void
    {
        $enrollment = $this->authorisedTarget('revoke');

        $this->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => 'A reason is required — it is recorded in the audit log.',
        ]);

        $this->runAction(
            fn (User $actor) => $suspendEnrollment->handle(
                enrollment: $enrollment,
                actor: $actor,
                reason: trim($this->reason),
            ),
            sprintf(
                'Access suspended for %s. Reinstate it at any time.',
                $this->studentName($enrollment),
            ),
            'suspend-enrollment',
        );
    }

    /**
     * Reinstatement needs no modal: it RESTORES access rather than removing
     * it, so the destructive-confirmation rule does not apply, and a
     * suspension is by definition already reversible.
     */
    public function reinstate(int $enrollmentId, ReinstateEnrollment $reinstateEnrollment): void
    {
        $this->actingOnId = $enrollmentId;
        $enrollment = $this->authorisedTarget('revoke');

        $this->runAction(
            fn (User $actor) => $reinstateEnrollment->handle(
                enrollment: $enrollment,
                actor: $actor,
            ),
            sprintf(
                'Access reinstated for %s.',
                $this->studentName($enrollment),
            ),
            null,
        );
    }

    public function cancelAction(): void
    {
        $this->reset(['actingOnId', 'reason', 'confirmation', 'refunded']);
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.admin.enrollments.enrollments-table', [
            'enrollments' => $this->rows(),
            'summary' => $this->summary(),
            'statusOptions' => EnrollmentStatus::cases(),
            'sourceOptions' => EnrollmentSource::cases(),
            'courseOptions' => $this->courseOptions(),
            'target' => $this->actingOn(),
            'confirmWord' => self::CONFIRM_WORD,
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | Internals
    |----------------------------------------------------------------------
    */

    private function prepareModal(int $enrollmentId, string $modal): void
    {
        $this->reset(['reason', 'confirmation', 'refunded']);
        $this->resetValidation();
        $this->actingOnId = $enrollmentId;

        $this->dispatch('open-modal', $modal);
    }

    /**
     * Resolve the enrolment under action and authorise it.
     *
     * Re-fetched and re-authorised on every call rather than trusted from
     * component state: `actingOnId` arrives from the browser and a user can
     * set it to anything (Rule 20 — hiding a control is never security).
     */
    private function authorisedTarget(string $ability): Enrollment
    {
        $enrollment = Enrollment::query()->with(['user:id,name,email', 'course:id,title'])->findOrFail($this->actingOnId);

        $this->authorize($ability, $enrollment);

        return $enrollment;
    }

    /**
     * The student's name for a confirmation message.
     *
     * `user` is typed nullable because the relation can be broken in principle
     * — a user row removed out from under an enrolment. That should not happen
     * (financial FKs are RESTRICT, NFR-DATA-05), but a flash message is not
     * the place to assert it, so this degrades to a neutral phrase instead of
     * risking a null property access in the success path.
     */
    private function studentName(Enrollment $enrollment): string
    {
        return $enrollment->user->name ?? 'the student';
    }

    /**
     * Run one lifecycle action, turning its refusal into a form error.
     *
     * The actions throw InvalidArgumentException for an illegal transition —
     * suspending an already-revoked enrolment, reinstating one that is not
     * suspended. Those are reachable from a stale page whose buttons no
     * longer match the current state, so they must read as an explanation
     * rather than a 500 (UI-GUIDE.md §11: error states say what to do next).
     *
     * @param  callable(User): Enrollment  $action
     */
    private function runAction(callable $action, string $message, ?string $modal): void
    {
        /** @var User $actor */
        $actor = auth()->user();

        try {
            $action($actor);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'reason' => $e->getMessage(),
            ]);
        }

        if ($modal !== null) {
            $this->dispatch('close-modal', $modal);
        }

        $this->reset(['actingOnId', 'reason', 'confirmation', 'refunded']);
        $this->resetValidation();

        session()->flash('status', $message);
    }
}
