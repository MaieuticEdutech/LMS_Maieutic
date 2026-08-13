<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Enrollments;

use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentSource;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Grant a student access to a course directly (FR-ENR-06, phases.md Phase 6).
 *
 * THE SECOND OF TWO WAYS ACCESS IS EVER CREATED. The first is a
 * signature-verified payment webhook (Phase 12). Both go through the same
 * single-owner action — this form has no privileged path of its own, and
 * `EnrollmentSource::AdminGrant` is what distinguishes the two afterwards
 * (Rule 1, ADR-006).
 *
 * WHY AN ADMIN GRANT EXISTS AT ALL, given that access is supposed to follow
 * payment: scholarships, staff accounts, a customer who paid by bank transfer,
 * and support putting right a payment that failed after the money left. Each
 * one is audited with the granting user recorded, which is what keeps this
 * from being a hole in the payment rule.
 *
 * IDEMPOTENT BY DELEGATION. Granting access somebody already has returns the
 * existing enrolment and sends no second email — that behaviour belongs to
 * `GrantEnrollment`, not to this form, so a double-submitted form cannot
 * produce a duplicate.
 */
#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Enrolments', 'url' => '/admin/enrollments'],
        ['label' => 'Grant access', 'url' => null],
    ],
])]
final class GrantEnrollmentForm extends Component
{
    public string $studentId = '';

    public string $courseId = '';

    public string $expiresAt = '';

    public string $reason = '';

    public function mount(): void
    {
        $this->authorize('grant', Enrollment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * `exists` is scoped to the student ROLE, not just to `users`.
             * Without the role clause an administrator could be enrolled as a
             * student by editing the select's value — validation is the only
             * thing standing between a submitted id and the action.
             */
            'studentId' => ['required', 'integer', 'exists:users,id'],
            'courseId' => ['required', 'integer', 'exists:courses,id'],

            // Optional, but if given it must be in the future: an enrolment
            // that expires yesterday grants nothing and would look like a bug.
            'expiresAt' => ['nullable', 'date', 'after:today'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'studentId.required' => 'Choose the student who should get access.',
            'courseId.required' => 'Choose the course to grant access to.',
            'expiresAt.after' => 'The end date must be in the future, otherwise the access would already have expired.',
            'reason.required' => 'A reason is required — it is recorded in the audit log against your name.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'studentId' => 'student',
            'courseId' => 'course',
            'expiresAt' => 'end date',
        ];
    }

    /**
     * Students only, and only ones who can actually use the access.
     *
     * @return Collection<int, User>
     */
    public function students(): Collection
    {
        return User::query()
            ->where('role', UserRole::Student)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /**
     * Every course, including drafts.
     *
     * Deliberately NOT restricted to published courses: granting a colleague
     * or a reviewer access to a draft in order to check it before launch is
     * one of the main reasons this screen exists. Publication controls whether
     * a course can be BOUGHT, which is a different question.
     *
     * @return Collection<int, Course>
     */
    public function courses(): Collection
    {
        return Course::query()->orderBy('title')->get(['id', 'title', 'status']);
    }

    public function save(GrantEnrollment $grantEnrollment): void
    {
        $this->authorize('grant', Enrollment::class);

        $validated = $this->validate();

        /** @var User $student */
        $student = User::query()->findOrFail($validated['studentId']);

        // Re-checked server-side rather than trusted from the select: the
        // options rendered are a convenience, never the authority (Rule 20).
        if (! $student->hasRole(UserRole::Student)) {
            $this->addError('studentId', 'Only a student account can be enrolled in a course.');

            return;
        }

        /** @var Course $course */
        $course = Course::query()->findOrFail($validated['courseId']);

        /** @var User $actor */
        $actor = auth()->user();

        $enrollment = $grantEnrollment->handle(
            student: $student,
            course: $course,
            source: EnrollmentSource::AdminGrant,
            actor: $actor,
            expiresAt: $this->expiresAt !== '' ? CarbonImmutable::parse($this->expiresAt) : null,
            reason: trim($this->reason),
        );

        /*
         * The action is idempotent, so a grant for access the student already
         * had is a no-op that returns the existing row. Saying "granted" would
         * be a small lie, and the administrator would have no way to tell the
         * two outcomes apart.
         *
         * `wasRecentlyCreated` is the framework's own answer to "did this
         * INSERT happen in this request?" — more reliable than comparing
         * timestamps, which would misreport a reactivation as a fresh grant.
         */
        $alreadyHadAccess = ! $enrollment->wasRecentlyCreated;

        session()->flash('status', $alreadyHadAccess
            ? sprintf('%s already had access to %s. Nothing was changed.', $student->name, $course->title)
            : sprintf('%s now has access to %s. They have been emailed.', $student->name, $course->title));

        $this->redirectRoute('admin.enrollments.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.enrollments.grant-enrollment-form', [
            'students' => $this->students(),
            'courses' => $this->courses(),
            'draftStatus' => CourseStatus::Draft,
        ]);
    }
}
