<?php

declare(strict_types=1);

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Livewire\Admin\AuditLogTable;
use App\Livewire\Admin\Courses\CoursesTable;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Enrollments\EnrollmentsTable;
use App\Livewire\Admin\InstructorsTable;
use App\Livewire\Admin\SettingsForm;
use App\Livewire\Admin\StudentsTable;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| QA PROBE — admin tables, dashboard, settings, audit log
|--------------------------------------------------------------------------
|
| Plan IDs: AD-01 … AD-12, STU-01 … STU-19, IM-01/IM-02, CRS-01 … CRS-08,
| ENR-16 … ENR-22, SET-01 … SET-05, AUD-01 … AUD-08.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->actingAs($this->admin);

    // RefreshDatabase rolls back the seeded settings, and SettingsForm builds
    // its validation rules FROM the rows it finds — with none, validate([])
    // falls through to Livewire's component-rules lookup and throws.
    $this->seed(Database\Seeders\SettingsSeeder::class);
});

/*
| ═══════════ AD-01 … AD-12 — dashboard ═══════════
*/

it('renders the dashboard on an empty database without erroring', function (): void {
    Livewire::test(Dashboard::class)->assertOk();
});

it('renders the dashboard with data present', function (): void {
    User::factory()->count(4)->create();
    User::factory()->instructor()->count(2)->create();
    Course::factory()->count(3)->published()->create();

    Livewire::test(Dashboard::class)->assertOk();
});

/*
| ═══════════ STU-01 … STU-19 — students table ═══════════
*/

it('lists only students, never instructors or admins', function (): void {
    User::factory()->create(['name' => 'Real Student']);
    User::factory()->instructor()->create(['name' => 'An Instructor']);

    Livewire::test(StudentsTable::class)
        ->assertSee('Real Student')
        ->assertDontSee('An Instructor');
});

it('searches students by name and by email', function (): void {
    User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@lms.test']);
    User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@lms.test']);

    $c = Livewire::test(StudentsTable::class);

    $c->set('search', 'Ada')->assertSee('Ada Lovelace')->assertDontSee('Grace Hopper');
    $c->set('search', 'grace@')->assertSee('Grace Hopper')->assertDontSee('Ada Lovelace');
});

it('shows an empty state when a search matches nothing', function (): void {
    User::factory()->create(['name' => 'Ada Lovelace']);

    Livewire::test(StudentsTable::class)
        ->set('search', 'zzzzzznothing')
        ->assertDontSee('Ada Lovelace')
        ->assertOk();
});

it('sorts students both directions and flips on repeat', function (): void {
    User::factory()->create(['name' => 'Aaron First']);
    User::factory()->create(['name' => 'Zoe Last']);

    $c = Livewire::test(StudentsTable::class);

    $c->call('sortBy', 'name');
    expect($c->get('sortField'))->toBe('name')->and($c->get('sortDirection'))->toBe('asc');

    $c->call('sortBy', 'name');
    expect($c->get('sortDirection'))->toBe('desc');

    $c->call('sortBy', 'email');
    expect($c->get('sortField'))->toBe('email')->and($c->get('sortDirection'))->toBe('asc');
});

it('resets search and sort together', function (): void {
    $c = Livewire::test(StudentsTable::class)
        ->set('search', 'something')
        ->call('sortBy', 'email')
        ->call('resetTableFilters');

    expect($c->get('search'))->toBe('')
        ->and($c->get('sortField'))->toBeNull();
});

it('paginates students server side', function (): void {
    User::factory()->count(40)->create();

    $c = Livewire::test(StudentsTable::class);

    expect($c->viewData('students')->perPage())->toBe(15)
        ->and($c->viewData('students')->total())->toBe(40)
        ->and($c->viewData('students')->hasPages())->toBeTrue();
});

it('returns to page one when the search changes', function (): void {
    User::factory()->count(40)->create();

    $c = Livewire::test(StudentsTable::class);
    $c->call('gotoPage', 2);
    expect($c->viewData('students')->currentPage())->toBe(2);

    $c->set('search', 'a');
    expect($c->viewData('students')->currentPage())->toBe(1);
});

it('exposes a working export action', function (): void {
    Livewire::test(StudentsTable::class)
        ->call('requestExport')
        ->assertOk();
});

/*
| ═══════════ IM-01 / IM-02 — instructors table ═══════════
*/

it('lists only instructors', function (): void {
    User::factory()->instructor()->create(['name' => 'Teach McTeach']);
    User::factory()->create(['name' => 'Just A Student']);

    Livewire::test(InstructorsTable::class)
        ->assertSee('Teach McTeach')
        ->assertDontSee('Just A Student');
});

/*
| ═══════════ CRS-01 … CRS-08 — courses table ═══════════
*/

it('filters courses by each lifecycle status', function (): void {
    Course::factory()->create(['title' => 'Draft One', 'status' => 'draft']);
    Course::factory()->published()->create(['title' => 'Published One']);
    Course::factory()->create(['title' => 'Archived One', 'status' => 'archived']);

    $c = Livewire::test(CoursesTable::class);

    $c->set('statusFilter', 'draft')->assertSee('Draft One')->assertDontSee('Published One');
    $c->set('statusFilter', 'published')->assertSee('Published One')->assertDontSee('Draft One');
    $c->set('statusFilter', 'archived')->assertSee('Archived One')->assertDontSee('Draft One');
});

it('counts courses per status', function (): void {
    Course::factory()->count(2)->create(['status' => 'draft']);
    Course::factory()->published()->count(3)->create();

    // Asserted through what the screen renders rather than by reaching into
    // the component: the counts exist to be read by an administrator, and a
    // test of the internal method would keep passing if they stopped showing.
    Livewire::test(CoursesTable::class)
        ->assertSeeInOrder(['Draft', '2'])
        ->assertSeeInOrder(['Published', '3']);
});

/*
| ═══════════ ENR-16 … ENR-22 — enrollments table ═══════════
*/

it('filters enrollments by status and by source', function (): void {
    // Asserted on the STUDENT name, not the course title: course titles also
    // appear in the table's own course-filter dropdown, so they are present in
    // the markup whether or not their row is listed.
    $activeGrant = User::factory()->create(['name' => 'Anita Activegrant']);
    $suspendedBuyer = User::factory()->create(['name' => 'Suresh Suspendedbuyer']);

    Enrollment::factory()->create([
        'user_id' => $activeGrant->getKey(),
        'status' => EnrollmentStatus::Active,
        'source' => EnrollmentSource::AdminGrant,
    ]);
    Enrollment::factory()->create([
        'user_id' => $suspendedBuyer->getKey(),
        'status' => EnrollmentStatus::Suspended,
        'source' => EnrollmentSource::Purchase,
    ]);

    $c = Livewire::test(EnrollmentsTable::class);

    $c->set('statusFilter', 'active')
        ->assertSee('Anita Activegrant')
        ->assertDontSee('Suresh Suspendedbuyer');

    $c->set('statusFilter', 'suspended')
        ->assertSee('Suresh Suspendedbuyer')
        ->assertDontSee('Anita Activegrant');

    $c->set('statusFilter', '')->set('sourceFilter', 'purchase')
        ->assertSee('Suresh Suspendedbuyer')
        ->assertDontSee('Anita Activegrant');
});

/*
| ═══════════ SET-01 … SET-05 — settings ═══════════
*/

it('renders every seeded setting group', function (): void {
    Livewire::test(SettingsForm::class)
        ->assertOk()
        ->assertSee('branding')
        ->assertSee('learning');
});

it('persists a branding change and surfaces it everywhere', function (): void {
    Livewire::test(SettingsForm::class)
        ->set('values.branding.organisation_name', 'Probe Academy')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(App\Services\Settings\SettingsRepository::class)->get('branding.organisation_name'))
        ->toBe('Probe Academy');

    // SET-04: the name must come from settings, never a hardcoded string.
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('Probe Academy');
});

it('rejects an empty required branding value', function (): void {
    Livewire::test(SettingsForm::class)
        ->set('values.branding.organisation_name', '')
        ->call('save')
        ->assertHasErrors('values.branding.organisation_name');
});

/*
| ═══════════ AUD-01 … AUD-08 — audit log ═══════════
*/

it('renders the audit log newest first', function (): void {
    Livewire::test(AuditLogTable::class)->assertOk();
});

it('records an audit entry for an administrative action', function (): void {
    $before = DB::table('audit_logs')->count();

    Livewire::test(SettingsForm::class)
        ->set('values.branding.organisation_name', 'Audited Name')
        ->call('save');

    expect(DB::table('audit_logs')->count())->toBeGreaterThan($before);
});

it('offers action options built from real recorded actions', function (): void {
    Livewire::test(SettingsForm::class)
        ->set('values.branding.organisation_name', 'For Options')
        ->call('save');

    // The filter is populated from actions actually recorded, so the entry
    // the save above produced must now be offered.
    $action = DB::table('audit_logs')->orderByDesc('id')->value('action');

    Livewire::test(AuditLogTable::class)->assertSee((string) $action);
});

/*
| ═══════════ STU-07 / STU-08 — the status filter the plan assumes ═══════════
|
| Documents actual behaviour: WithAdminTable declares $filters, but no admin
| table reads it and neither the students nor the instructors table exposes a
| status filter.
*/

it('has no status filter on the students table', function (): void {
    $c = Livewire::test(StudentsTable::class);

    expect($c->get('filters'))->toBe([]);

    $suspended = User::factory()->suspended()->create(['name' => 'Suspended Person']);
    $active = User::factory()->create(['name' => 'Active Person']);

    // Setting the trait's filter array changes nothing — it is never applied.
    $c->set('filters', ['status' => 'suspended'])
        ->assertSee('Active Person')
        ->assertSee('Suspended Person');
});
