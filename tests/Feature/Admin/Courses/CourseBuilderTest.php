<?php

declare(strict_types=1);

use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Livewire\Admin\Courses\CourseBuilder;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Livewire\Livewire;

it('creates a course as a draft and redirects into its builder', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(CourseBuilder::class)
        ->set('title', 'Full-Stack Web Development')
        ->set('priceRupees', '9999')
        ->call('save')
        ->assertRedirect();

    $course = Course::query()->where('title', 'Full-Stack Web Development')->firstOrFail();

    expect($course->status)->toBe(CourseStatus::Draft)
        ->and($course->price_amount)->toBe(999900)
        ->and($course->created_by)->toBe($admin->id);
});

it('converts a rupee price into paise on the way in', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(CourseBuilder::class)
        ->set('title', 'Priced Course')
        ->set('priceRupees', '1499.50')
        ->call('save');

    $course = Course::query()->where('title', 'Priced Course')->firstOrFail();

    expect($course->price_amount)->toBe(149950);
});

it('updates an existing course without redirecting', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->create(['title' => 'Old title']);

    Livewire::test(CourseBuilder::class, ['course' => $course])
        ->set('title', 'New title')
        ->call('save')
        ->assertNoRedirect();

    expect($course->refresh()->title)->toBe('New title');
});

it('does not resend price_amount to UpdateCourse when the price is unchanged', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->create(['price_amount' => 500000]);
    $originalPrice = $course->price_amount;

    Livewire::test(CourseBuilder::class, ['course' => $course])
        ->set('title', $course->title.' (edited)')
        ->call('save');

    expect($course->refresh()->price_amount)->toBe($originalPrice);
});

it('shows a live publish checklist that matches CoursePublishValidator', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->create(['description' => null]);

    Livewire::test(CourseBuilder::class, ['course' => $course])
        ->assertSee('needs a description');
});

it('publishes a course once every requirement is met', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->create(['description' => 'Complete description.']);
    $course->thumbnail_path = 'thumb.png';
    $course->save();
    $module = Module::factory()->forCourse($course)->published()->create();
    Lesson::factory()->forModule($module)->published()->create();

    Livewire::test(CourseBuilder::class, ['course' => $course])
        ->call('publish');

    expect($course->refresh()->status)->toBe(CourseStatus::Published);
});

it('refuses to publish when blockers remain, and does not throw', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->create(['description' => null]);

    Livewire::test(CourseBuilder::class, ['course' => $course])
        ->call('publish')
        ->assertOk();

    expect($course->refresh()->status)->toBe(CourseStatus::Draft);
});

it('unpublishes without touching enrolled students access', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->published()->create();
    $enrollment = Enrollment::factory()->for($course)->create();

    Livewire::test(CourseBuilder::class, ['course' => $course])
        ->call('unpublish');

    expect($course->refresh()->status)->toBe(CourseStatus::Draft)
        ->and($enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);
});

it('archives a course', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->published()->create();

    Livewire::test(CourseBuilder::class, ['course' => $course])
        ->call('archive');

    expect($course->refresh()->status)->toBe(CourseStatus::Archived);
});

it('deletes a course with no enrollments', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->create();

    Livewire::test(CourseBuilder::class, ['course' => $course])
        ->call('delete')
        ->assertRedirect(route('admin.courses.index'));

    expect($course->refresh()->trashed())->toBeTrue();
});

it('refuses to delete a course with enrollments, leaving it intact', function (): void {
    // CourseDeletionException's message content (naming the archive
    // alternative) is already proven at the action level in
    // tests/Feature/Catalogue/CourseLifecycleTest.php — this test only
    // needs to prove the Livewire wiring doesn't let the delete through.
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->create();
    Enrollment::factory()->for($course)->create();

    Livewire::test(CourseBuilder::class, ['course' => $course])
        ->call('delete')
        ->assertNoRedirect();

    expect($course->refresh()->trashed())->toBeFalse();
});

it('denies an instructor and a student from the builder', function (): void {
    $course = Course::factory()->create();

    $instructor = User::factory()->instructor()->create();
    $this->actingAs($instructor)->get(route('admin.courses.builder', $course))->assertForbidden();

    $student = User::factory()->student()->create();
    $this->actingAs($student)->get(route('admin.courses.builder', $course))->assertForbidden();
});

it('denies creating a course to anyone but a super admin', function (): void {
    $instructor = User::factory()->instructor()->create();
    $this->actingAs($instructor)->get(route('admin.courses.create'))->assertForbidden();
});

it('lists categories for the meta form', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $category = Category::factory()->create(['name' => 'Mathematics']);

    Livewire::test(CourseBuilder::class)->assertSee('Mathematics');
});
