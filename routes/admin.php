<?php

declare(strict_types=1);

use App\Livewire\Admin\Assessments\AssessmentBuilder;
use App\Livewire\Admin\Assessments\AssessmentsTable;
use App\Livewire\Admin\AuditLogTable;
use App\Livewire\Admin\Courses\CategoriesTable;
use App\Livewire\Admin\Courses\CourseBuilder;
use App\Livewire\Admin\Courses\CoursesTable;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\EmailLogTable;
use App\Livewire\Admin\Enrollments\EnrollmentsTable;
use App\Livewire\Admin\Enrollments\GrantEnrollmentForm;
use App\Livewire\Admin\InstructorDetail;
use App\Livewire\Admin\InstructorForm;
use App\Livewire\Admin\InstructorsTable;
use App\Livewire\Admin\QueueHealth;
use App\Livewire\Admin\SettingsForm;
use App\Livewire\Admin\StudentDetail;
use App\Livewire\Admin\StudentForm;
use App\Livewire\Admin\StudentsTable;
use App\Livewire\Instructor\Assessments\Results;
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
        /*
         * Categories. The table, model, policy and factory shipped in Phase 3
         * and the Course Builder has always offered the field, but nothing
         * could populate the list — so the dropdown was permanently empty on
         * a fresh database. This is the screen that was missing.
         */
        Route::get('/categories', CategoriesTable::class)->name('categories.index');

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

        /*
         * Phase 8 — assessments. No standalone "create" route: an
         * assessment is always created from its attach point (a quiz
         * lesson's editor, a course's final-test section) via a one-line
         * action that redirects straight here to continue building —
         * same shape as CourseBuilder's own create flow.
         */
        Route::get('/assessments', AssessmentsTable::class)->name('assessments.index');
        Route::get('/assessments/{assessment}', AssessmentBuilder::class)->name('assessments.builder');

        /*
         * Phase 10 — Results is shared with routes/instructor.php (one
         * implementation, chrome chosen dynamically). See its docblock.
         *
         * Registered under the assessments block it belongs to rather than at
         * the end of the group: the URL is a child of {assessment}, and a
         * route that reads as unrelated to its neighbours is how a duplicate
         * gets added later.
         */
        Route::get('/assessments/{assessment}/results', Results::class)->name('assessments.results');

        /*
         * Phase 11 — operations.
         *
         * Both are read-mostly views over things the system did rather than
         * over domain records: `email_logs` is written by the mail transport
         * events, `failed_jobs` by the queue. The one exception is retrying a
         * failed job, which is a Livewire action for the same reason enrolment
         * state changes are — a GET route that re-runs queued work would be
         * both wrong and CSRF-exempt by nature.
         *
         * The nav is guarded by Route::has(), so registering these is what
         * makes their links appear.
         */
        Route::get('/email-log', EmailLogTable::class)->name('email-log.index');
        Route::get('/queue-health', QueueHealth::class)->name('queue-health.index');
    });
