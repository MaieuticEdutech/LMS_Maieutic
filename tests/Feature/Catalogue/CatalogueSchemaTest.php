<?php

declare(strict_types=1);

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Phase 3 · Track A — schema invariants
|--------------------------------------------------------------------------
|
| These test the DATABASE, not the application. Application-level checks are a
| convenience; the constraint is the guarantee (architecture.md §6.5). Each
| test bypasses Eloquent deliberately so it proves PostgreSQL refuses the bad
| value, not that a model happened to validate it.
|
*/

/*
| ADR-014 — ALL V1 COURSES ARE PAID.
|
| The most consequential constraint in this slice: it is what makes "there is
| no free-enrollment path" structurally true rather than merely intended.
*/
it('refuses a course priced at zero', function (): void {
    $course = Course::factory()->create();

    expect(fn () => DB::table('courses')->where('id', $course->id)->update(['price_amount' => 0]))
        ->toThrow(QueryException::class);
});

it('refuses a negatively priced course', function (): void {
    $course = Course::factory()->create();

    expect(fn () => DB::table('courses')->where('id', $course->id)->update(['price_amount' => -100]))
        ->toThrow(QueryException::class);
});

it('refuses a zero-byte media file', function (): void {
    // A zero-byte upload means the pipeline failed silently.
    $file = MediaFile::factory()->create();

    expect(fn () => DB::table('media_files')->where('id', $file->id)->update(['size_bytes' => 0]))
        ->toThrow(QueryException::class);
});

/*
| CHECK constraints mirroring the PHP enums (ADR-012).
*/
it('rejects illegal enum values at the database level', function (string $table, string $column, string $bad): void {
    $id = match ($table) {
        'courses' => Course::factory()->create()->id,
        'lessons' => Lesson::factory()->create()->id,
        'media_files' => MediaFile::factory()->create()->id,
        default => null,
    };

    expect(fn () => DB::table($table)->where('id', $id)->update([$column => $bad]))
        ->toThrow(QueryException::class);
})->with([
    'course status' => ['courses', 'status', 'sideways'],
    'course level' => ['courses', 'level', 'expert'],
    'lesson type' => ['lessons', 'type', 'hologram'],
    'media purpose' => ['media_files', 'purpose', 'meme'],
]);

it('accepts every enum value the application can produce', function (): void {
    foreach (CourseStatus::cases() as $status) {
        foreach (CourseLevel::cases() as $level) {
            $course = Course::factory()->create();
            $course->forceFill(['status' => $status, 'level' => $level])->save();
            $course->refresh();

            expect($course->status)->toBe($status)->and($course->level)->toBe($level);
        }
    }

    foreach (LessonType::cases() as $type) {
        $lesson = Lesson::factory()->create();
        $lesson->forceFill(['type' => $type])->save();

        expect($lesson->refresh()->type)->toBe($type);
    }

    foreach (MediaPurpose::cases() as $purpose) {
        $file = MediaFile::factory()->create();
        $file->forceFill(['purpose' => $purpose])->save();

        expect($file->refresh()->purpose)->toBe($purpose);
    }
});

/*
| Uniqueness.
*/
it('enforces a unique course slug', function (): void {
    Course::factory()->create(['slug' => 'taken-slug']);

    expect(fn () => Course::factory()->create(['slug' => 'taken-slug']))
        ->toThrow(QueryException::class);
});

it('enforces a unique lesson slug within a module but allows reuse across modules', function (): void {
    $moduleA = Module::factory()->create();
    $moduleB = Module::factory()->create();

    Lesson::factory()->forModule($moduleA)->create(['slug' => 'intro']);

    // Same slug, different module — legitimate, and must be allowed.
    Lesson::factory()->forModule($moduleB)->create(['slug' => 'intro']);

    expect(fn () => Lesson::factory()->forModule($moduleA)->create(['slug' => 'intro']))
        ->toThrow(QueryException::class);
});

it('enforces one instructor assignment per course', function (): void {
    $course = Course::factory()->create();
    $instructor = User::factory()->instructor()->create();

    $course->instructors()->attach($instructor);

    // A double assignment would duplicate every row of every
    // instructor-scoped listing.
    expect(fn () => DB::table('course_instructor')->insert([
        'course_id' => $course->id,
        'user_id' => $instructor->id,
        'role_in_course' => 'lead',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('enforces a unique media ulid', function (): void {
    $file = MediaFile::factory()->create();

    expect(fn () => MediaFile::factory()->create(['ulid' => $file->ulid]))
        ->toThrow(QueryException::class);
});

/*
| Cascade behaviour — deleting content must clean up, deleting PEOPLE must not.
*/
it('cascades course deletion down through modules to lessons', function (): void {
    $course = Course::factory()->create();
    $module = Module::factory()->forCourse($course)->create();
    $lesson = Lesson::factory()->forModule($module)->create();

    // Hard delete — the soft delete at the model layer never reaches the FK.
    DB::table('courses')->where('id', $course->id)->delete();

    expect(DB::table('modules')->where('id', $module->id)->exists())->toBeFalse()
        ->and(DB::table('lessons')->where('id', $lesson->id)->exists())->toBeFalse();
});

it('keeps a course when its creator is deleted', function (): void {
    // Deleting a user must never delete the courses they authored.
    $admin = User::factory()->superAdmin()->create();
    $course = Course::factory()->create(['created_by' => $admin->id]);

    $admin->forceDelete();

    $course->refresh();

    expect($course->exists)->toBeTrue()
        ->and($course->created_by)->toBeNull();
});

it('keeps courses when their category is deleted', function (): void {
    $category = Category::factory()->create();
    $course = Course::factory()->inCategory($category)->create();

    $category->delete();

    expect($course->refresh()->category_id)->toBeNull()
        ->and($course->exists)->toBeTrue();
});

it('orphans child categories to the top level rather than deleting them', function (): void {
    $parent = Category::factory()->create();
    $child = Category::factory()->childOf($parent)->create();

    $parent->delete();

    expect($child->refresh()->parent_id)->toBeNull()
        ->and($child->exists)->toBeTrue();
});

it('removes the instructor assignment when a course is hard deleted', function (): void {
    $course = Course::factory()->create();
    $instructor = User::factory()->instructor()->create();
    $course->instructors()->attach($instructor);

    DB::table('courses')->where('id', $course->id)->delete();

    expect(DB::table('course_instructor')->where('user_id', $instructor->id)->exists())->toBeFalse()
        // The instructor themselves survives.
        ->and(User::query()->find($instructor->id))->not->toBeNull();
});

/*
| Soft deletes — recoverability (FR-CRS-06).
*/
it('soft deletes courses and lessons', function (): void {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create();

    $course->delete();
    $lesson->delete();

    expect(Course::query()->find($course->id))->toBeNull()
        ->and(Course::withTrashed()->find($course->id))->not->toBeNull()
        ->and(Lesson::query()->find($lesson->id))->toBeNull()
        ->and(Lesson::withTrashed()->find($lesson->id))->not->toBeNull();
});

/*
| Mass-assignment guards (NFR-SEC-07).
*/
it('refuses to mass-assign guarded course fields', function (array $payload): void {
    expect(fn () => Course::factory()->create()->fill($payload))
        ->toThrow(Exception::class);
})->with([
    'status' => [['status' => CourseStatus::Published]],
    'price' => [['price_amount' => 1]],
    'creator' => [['created_by' => 1]],
]);

it('refuses to mass-assign a media file disk or path', function (array $payload): void {
    // If these were fillable, a request could point a media record at an
    // arbitrary file on disk — including another course's.
    expect(fn () => MediaFile::factory()->create()->fill($payload))
        ->toThrow(Exception::class);
})->with([
    'disk' => [['disk' => 'public']],
    'path' => [['path' => '../../etc/passwd']],
]);
