<?php

declare(strict_types=1);

use App\Livewire\Admin\AuditLogTable;
use App\Livewire\Admin\Courses\CourseBuilder;
use App\Livewire\Admin\Courses\CoursesTable;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Enrollments\EnrollmentsTable;
use App\Livewire\Admin\Enrollments\GrantEnrollmentForm;
use App\Livewire\Admin\InstructorDetail;
use App\Livewire\Admin\InstructorForm;
use App\Livewire\Admin\InstructorsTable;
use App\Livewire\Admin\SettingsForm;
use App\Livewire\Admin\StudentDetail;
use App\Livewire\Admin\StudentForm;
use App\Livewire\Admin\StudentsTable;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administrator Area — SUPER ADMIN ONLY
|--------------------------------------------------------------------------
|
| Every route in this file MUST sit behind, in this order:
|     ->middleware(['auth', 'active', 'role:super_admin'])
|
| Middleware is the coarse gate only. It is NOT sufficient on its own: each
| controller/Livewire action must additionally authorise the specific record
| through its Policy (FR-RBAC-02, FR-RBAC-03, architecture.md §8.2). Middleware
| answers "may this kind of user be here?"; the policy answers "may THIS user
| touch THIS record?".
|
| Delivered by:
|   Phase 4  — dashboard, student & instructor management, instructor↔course
|              assignment, settings, audit log viewer
|   Phase 5  — course CRUD, Course Builder, content upload
|   Phase 6  — enrollment grant/revoke, enrollment listing
|   Phase 8  — assessment & question management
|   Phase 12 — orders, payments, webhook event log
|   Phase 13 — reports and exports
|
| The `active` and `role` middleware aliases are registered in Phase 2. This
| file is intentionally empty until then: registering routes behind middleware
| that does not yet exist would fail at boot.
|
*/

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'active', 'role:super_admin'])
    ->group(static function (): void {
        // Phase 4 Checkpoint 2: the real dashboard, replacing the Phase 2
        // placeholder. 'home' is now 'dashboard' — this route serves genuine
        // KPI content, so the name should say so.
        Route::get('/', Dashboard::class)->name('dashboard');

        // Phase 4 Checkpoint 3. /create and /{student}/edit both route to
        // StudentForm — it branches on whether a model was bound, the same
        // create-or-edit pattern every admin form in this area will follow.
        Route::get('/students', StudentsTable::class)->name('students.index');
        Route::get('/students/create', StudentForm::class)->name('students.create');
        Route::get('/students/{student}/edit', StudentForm::class)->name('students.edit');
        Route::get('/students/{student}', StudentDetail::class)->name('students.show');

        // Phase 4 Checkpoint 4. CourseInstructorAssignment is embedded inside
        // InstructorDetail's view, not routed to directly.
        Route::get('/instructors', InstructorsTable::class)->name('instructors.index');
        Route::get('/instructors/create', InstructorForm::class)->name('instructors.create');
        Route::get('/instructors/{instructor}/edit', InstructorForm::class)->name('instructors.edit');
        Route::get('/instructors/{instructor}', InstructorDetail::class)->name('instructors.show');

        // Phase 4 Checkpoint 5.
        Route::get('/settings', SettingsForm::class)->name('settings.index');

        // Phase 4 Checkpoint 6 shipped this read-only; Phase 5 adds create
        // and the Course Builder. CourseBuilder is a single combined
        // meta + structure screen (architecture.md §9.3) — there is no
        // separate edit route, "editing" a course always means the builder.
        Route::get('/courses', CoursesTable::class)->name('courses.index');
        Route::get('/courses/create', CourseBuilder::class)->name('courses.create');
        Route::get('/courses/{course}/builder', CourseBuilder::class)->name('courses.builder');
        Route::get('/audit-log', AuditLogTable::class)->name('audit-log.index');

        /*
         * Phase 6 — enrolments (Track C).
         *
         * Suspend, reinstate and revoke are Livewire actions on the table
         * rather than routes of their own: they are state changes on a record
         * already on screen, and a GET route that mutates access would be both
         * wrong and CSRF-exempt by nature.
         *
         * The admin layout's nav is guarded by Route::has(), so registering
         * these is what makes the "Enrolments" link appear — no edit to the
         * shell, which is Track B's file.
         */
        Route::get('/enrollments', EnrollmentsTable::class)->name('enrollments.index');
        Route::get('/enrollments/create', GrantEnrollmentForm::class)->name('enrollments.create');
    });
