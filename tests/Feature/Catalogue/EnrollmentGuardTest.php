<?php

declare(strict_types=1);

use App\Actions\Catalog\ArchiveCourse;
use App\Actions\Catalog\DeleteCourse;
use App\Actions\Catalog\UnpublishCourse;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Exceptions\CourseDeletionException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Phase 5 · Enrollment guards on course lifecycle (FR-CRS-06)
|--------------------------------------------------------------------------
|
| These two requirements were specified in Phase 5 but could not be written
| then — the `enrollments` table belonged to another track and did not exist.
| Rather than stub the check, it was left explicitly undone. This closes it.
|
| Both come down to the same principle: a student who paid for a course must
| not lose it because an administrator tidied up. Course lifecycle operations
| are about the CATALOGUE, and access is decided somewhere else entirely.
|
*/

beforeEach(function (): void {
    Queue::fake();

    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();
    $this->course = Course::factory()->published()->create();
});

/**
 * A real enrollment row. Created directly rather than through
 * GrantEnrollment: this is a fixture, and these tests are about the course
 * lifecycle, not about how the enrollment came to exist.
 */
function enrol(User $student, Course $course, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
{
    $enrollment = new Enrollment;

    $enrollment->forceFill([
        'user_id' => $student->getKey(),
        'course_id' => $course->getKey(),
        'source' => App\Enums\EnrollmentSource::Purchase,
        'status' => $status,
        'enrolled_at' => now(),
    ])->save();

    return $enrollment;
}

/*
| ═══════════ FR-CRS-06 — A COURSE WITH ENROLLMENTS IS NOT DELETABLE ═══════
*/
it('refuses to delete a course that has enrollments', function (): void {
    enrol($this->student, $this->course);

    expect(fn () => app(DeleteCourse::class)->handle($this->course, $this->admin))
        ->toThrow(CourseDeletionException::class);

    // Nothing happened: not even a soft delete, and no cleanup queued.
    expect(Course::query()->find($this->course->id))->not->toBeNull();
    Queue::assertNothingPushed();
});

it('refuses even when the enrollment is no longer active', function (EnrollmentStatus $status): void {
    // A refunded or expired enrollment is still a commercial record — evidence
    // that money moved. Deleting the course would orphan it.
    enrol($this->student, $this->course, $status);

    expect(fn () => app(DeleteCourse::class)->handle($this->course, $this->admin))
        ->toThrow(CourseDeletionException::class);
})->with([
    'refunded' => EnrollmentStatus::Refunded,
    'expired' => EnrollmentStatus::Expired,
    'suspended' => EnrollmentStatus::Suspended,
    'completed' => EnrollmentStatus::Completed,
]);

it('still deletes a course nobody has enrolled in', function (): void {
    // The guard must not be so broad that it blocks the ordinary case of
    // removing a course created by mistake.
    app(DeleteCourse::class)->handle($this->course, $this->admin);

    expect(Course::query()->find($this->course->id))->toBeNull()
        ->and(Course::withTrashed()->find($this->course->id))->not->toBeNull();
});

it('names archiving as the alternative in the error', function (): void {
    enrol($this->student, $this->course);

    try {
        app(DeleteCourse::class)->handle($this->course, $this->admin);
        $this->fail('Expected CourseDeletionException.');
    } catch (CourseDeletionException $e) {
        // A refusal that offers no way forward is one people route around.
        expect($e->getMessage())->toContain('Archive')
            ->and($e->getMessage())->toContain($this->course->title);
    }
});

it('lets an enrolled course be archived instead', function (): void {
    enrol($this->student, $this->course);

    $archived = app(ArchiveCourse::class)->handle($this->course, $this->admin);

    expect($archived->status)->toBe(CourseStatus::Archived)
        // The student keeps everything.
        ->and(Enrollment::query()->where('course_id', $this->course->id)->count())->toBe(1);
});

/*
| ═══════════ UNPUBLISHING DOES NOT TOUCH ENROLLMENTS ═══════════
|
| Specified in Phase 5's testing requirements and untestable until now. The
| behaviour is correct by design — UnpublishCourse never looks at enrollments
| — but "correct by design" without a test is what regresses two phases later,
| when someone adds a well-meaning cascade.
*/
it('leaves every enrollment untouched when a course is unpublished', function (): void {
    $enrollment = enrol($this->student, $this->course);

    // Compared as scalars, not as models: `only()` returns Carbon instances,
    // and a refreshed model builds new ones, so an identity comparison fails
    // on objects that hold the same value.
    $before = [
        'status' => $enrollment->status->value,
        'enrolled_at' => $enrollment->enrolled_at->toIso8601String(),
        'expires_at' => $enrollment->expires_at?->toIso8601String(),
        'revoked_at' => $enrollment->revoked_at?->toIso8601String(),
        'updated_at' => $enrollment->updated_at?->toIso8601String(),
    ];

    app(UnpublishCourse::class)->handle($this->course, $this->admin);

    $enrollment->refresh();

    expect($this->course->refresh()->status)->toBe(CourseStatus::Draft)
        ->and([
            'status' => $enrollment->status->value,
            'enrolled_at' => $enrollment->enrolled_at->toIso8601String(),
            'expires_at' => $enrollment->expires_at?->toIso8601String(),
            'revoked_at' => $enrollment->revoked_at?->toIso8601String(),
            // updated_at included deliberately: if a future cascade so much as
            // re-saved the row, this catches it even when no value changed.
            'updated_at' => $enrollment->updated_at?->toIso8601String(),
        ])->toBe($before);
});

it('leaves every enrollment untouched when a course is archived', function (): void {
    $enrollment = enrol($this->student, $this->course);

    app(ArchiveCourse::class)->handle($this->course, $this->admin);

    expect($enrollment->refresh()->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->revoked_at)->toBeNull();
});

it('does not change how many enrollments exist', function (): void {
    enrol($this->student, $this->course);
    enrol(User::factory()->create(), $this->course);

    app(UnpublishCourse::class)->handle($this->course, $this->admin);

    // Withdrawing a course from sale is a catalogue decision. It says nothing
    // about the people who already bought it.
    expect(Enrollment::query()->where('course_id', $this->course->id)->count())->toBe(2);
});
