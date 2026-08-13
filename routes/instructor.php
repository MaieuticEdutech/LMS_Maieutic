<?php

declare(strict_types=1);

use App\Livewire\Admin\Assessments\AssessmentBuilder;
use App\Livewire\Instructor\Assessments\AssessmentsTable;
use App\Livewire\Instructor\Assessments\Results;
use App\Livewire\Instructor\CourseDetail;
use App\Livewire\Instructor\CoursesList;
use App\Livewire\Instructor\Dashboard;
use App\Livewire\Instructor\StudentProgressDetail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Instructor Area — ASSIGNED COURSES ONLY
|--------------------------------------------------------------------------
|
| Every route in this file MUST sit behind:
|     ->middleware(['auth', 'active', 'role:instructor'])
|
| THE SCOPING RULE (FR-RBAC-04, FR-INS-09, AC-03):
| An instructor may only ever reach data belonging to a course they are
| assigned to via the `course_instructor` pivot. Every query in this area
| MUST begin from Course::assignedTo($user) rather than Course::query(), so
| that scope leakage requires actively bypassing the standard entry point
| rather than merely forgetting a WHERE clause (architecture.md §8.4).
|
| TWO HARD PROHIBITIONS FOR V1:
|   1. Instructors do NOT author course content — no course, module, lesson
|      or media routes belong here (FR-INS-08). They author assessments only.
|   2. Instructors see NO financial data — no order, payment or revenue route
|      may ever appear in this file (FR-INS-10). OrderPolicy and PaymentPolicy
|      deny them explicitly.
|
| AssessmentBuilder and Results are SHARED with routes/admin.php — one
| implementation, not a near-duplicate, since AssessmentPolicy already
| authorises both an assigned instructor and a super admin the same way.
| Only the chrome (layout, breadcrumbs) differs, chosen dynamically inside
| those components. See Admin\Assessments\AssessmentBuilder's docblock.
|
| Delivered by Phase 10 — dashboard, assigned courses, enrolled students,
| assessment authoring, results, statistics, and per-student/per-course
| progress (FR-INS-02, FR-INS-03, FR-INS-07) now that Phase 9's
| ProgressCalculator is on main.
|
*/

Route::prefix('instructor')
    ->name('instructor.')
    ->middleware(['auth', 'active', 'role:instructor'])
    ->group(static function (): void {
        Route::get('/', Dashboard::class)->name('home');

        Route::get('/courses', CoursesList::class)->name('courses.index');
        Route::get('/courses/{course}', CourseDetail::class)->name('courses.show');
        Route::get('/courses/{course}/students/{enrollment}', StudentProgressDetail::class)->name('courses.students.progress');

        Route::get('/assessments', AssessmentsTable::class)->name('assessments.index');
        Route::get('/assessments/{assessment}', AssessmentBuilder::class)->name('assessments.builder');
        Route::get('/assessments/{assessment}/results', Results::class)->name('assessments.results');
    });
