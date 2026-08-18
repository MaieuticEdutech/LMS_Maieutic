<?php

declare(strict_types=1);

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Livewire\Admin\Courses\CoursesTable;
use App\Livewire\Admin\Enrollments\EnrollmentsTable;
use App\Livewire\Admin\InstructorsTable;
use App\Livewire\Admin\StudentsTable;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Admin table filtering, and the reset that has to reach it
|--------------------------------------------------------------------------
|
| WithAdminTable used to declare an untyped `array $filters` that no table
| ever populated, alongside a resetTableFilters() that cleared it. The visible
| consequence: the Enrolments screen's "Clear filters" button cleared the
| search box and left status, source and course exactly as they were.
|
| Filters are now typed properties declared per table, and reset finds them
| through filterProperties(). These cover both halves.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->actingAs($this->admin);
});

/*
| ═══════════ The reset bug ═══════════
*/

it('clears every enrollment filter, not just the search box', function (): void {
    $c = Livewire::test(EnrollmentsTable::class)
        ->set('search', 'anything')
        ->set('statusFilter', 'active')
        ->set('sourceFilter', 'purchase')
        ->set('courseFilter', '1')
        ->call('sortBy', 'created_at')
        ->call('resetTableFilters');

    expect($c->get('search'))->toBe('')
        ->and($c->get('statusFilter'))->toBe('')
        ->and($c->get('sourceFilter'))->toBe('')
        ->and($c->get('courseFilter'))->toBe('')
        ->and($c->get('sortField'))->toBeNull();
});

it('clears the course status filter on reset', function (): void {
    $c = Livewire::test(CoursesTable::class)
        ->set('statusFilter', 'draft')
        ->call('resetTableFilters');

    expect($c->get('statusFilter'))->toBe('');
});

it('clears the student status filter on reset', function (): void {
    $c = Livewire::test(StudentsTable::class)
        ->set('search', 'x')
        ->set('statusFilter', 'suspended')
        ->call('resetTableFilters');

    expect($c->get('search'))->toBe('')
        ->and($c->get('statusFilter'))->toBe('');
});

/*
| ═══════════ The filter that was missing entirely ═══════════
*/

it('filters students by each lifecycle status', function (): void {
    $active = User::factory()->create(['name' => 'Active Student']);
    $suspended = User::factory()->suspended()->create(['name' => 'Suspended Student']);

    $c = Livewire::test(StudentsTable::class);

    // Unfiltered, both are listed.
    $c->assertSee('Active Student')->assertSee('Suspended Student');

    $c->set('statusFilter', $suspended->status->value)
        ->assertSee('Suspended Student')
        ->assertDontSee('Active Student');

    $c->set('statusFilter', $active->status->value)
        ->assertSee('Active Student')
        ->assertDontSee('Suspended Student');
});

it('filters instructors by status', function (): void {
    User::factory()->instructor()->create(['name' => 'Working Instructor']);
    $suspended = User::factory()->instructor()->suspended()->create(['name' => 'Blocked Instructor']);

    Livewire::test(InstructorsTable::class)
        ->set('statusFilter', $suspended->status->value)
        ->assertSee('Blocked Instructor')
        ->assertDontSee('Working Instructor');
});

it('combines search and status filter with AND', function (): void {
    User::factory()->suspended()->create(['name' => 'Asha Suspended']);
    User::factory()->suspended()->create(['name' => 'Bala Suspended']);
    User::factory()->create(['name' => 'Asha Active']);

    Livewire::test(StudentsTable::class)
        ->set('search', 'Asha')
        ->set('statusFilter', 'suspended')
        ->assertSee('Asha Suspended')
        ->assertDontSee('Bala Suspended')   // right status, wrong name
        ->assertDontSee('Asha Active');     // right name, wrong status
});

it('returns to page one when the status filter changes', function (): void {
    User::factory()->count(40)->create();

    $c = Livewire::test(StudentsTable::class);
    $c->call('gotoPage', 2);
    expect($c->viewData('students')->currentPage())->toBe(2);

    $c->set('statusFilter', 'active');
    expect($c->viewData('students')->currentPage())->toBe(1);
});

it('keeps the status filter applied while sorting', function (): void {
    User::factory()->suspended()->create(['name' => 'Zeta Suspended']);
    User::factory()->create(['name' => 'Alpha Active']);

    Livewire::test(StudentsTable::class)
        ->set('statusFilter', 'suspended')
        ->call('sortBy', 'name')
        ->assertSee('Zeta Suspended')
        ->assertDontSee('Alpha Active');
});

/*
| ═══════════ The dead abstraction is gone ═══════════
*/

it('no longer exposes an untyped filters array', function (): void {
    // A generic array nothing populated, on a trait whose own docblock says a
    // second implementation of the pattern is a defect.
    expect(property_exists(StudentsTable::class, 'filters'))->toBeFalse()
        ->and(property_exists(EnrollmentsTable::class, 'filters'))->toBeFalse();
});

it('still filters enrollments correctly after the refactor', function (): void {
    $bought = User::factory()->create(['name' => 'Bought Access']);
    $granted = User::factory()->create(['name' => 'Granted Access']);

    Enrollment::factory()->create([
        'user_id' => $bought->getKey(),
        'status' => EnrollmentStatus::Active,
        'source' => EnrollmentSource::Purchase,
    ]);
    Enrollment::factory()->create([
        'user_id' => $granted->getKey(),
        'status' => EnrollmentStatus::Suspended,
        'source' => EnrollmentSource::AdminGrant,
    ]);

    $c = Livewire::test(EnrollmentsTable::class);

    $c->set('sourceFilter', 'purchase')
        ->assertSee('Bought Access')
        ->assertDontSee('Granted Access');

    $c->call('resetTableFilters')
        ->set('statusFilter', 'suspended')
        ->assertSee('Granted Access')
        ->assertDontSee('Bought Access');
});

it('offers a course filter that still narrows to one course', function (): void {
    $course = Course::factory()->published()->create(['title' => 'Targeted Course']);
    $other = Course::factory()->published()->create(['title' => 'Other Course']);

    $inTarget = User::factory()->create(['name' => 'In Target']);
    $inOther = User::factory()->create(['name' => 'In Other']);

    Enrollment::factory()->create(['user_id' => $inTarget->getKey(), 'course_id' => $course->getKey()]);
    Enrollment::factory()->create(['user_id' => $inOther->getKey(), 'course_id' => $other->getKey()]);

    Livewire::test(EnrollmentsTable::class)
        ->set('courseFilter', (string) $course->getKey())
        ->assertSee('In Target')
        ->assertDontSee('In Other');
});
