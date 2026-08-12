<?php

declare(strict_types=1);

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
| Delivered by Phase 10 — dashboard, assigned courses, enrolled students,
| assessment authoring, results and statistics, per-student progress.
|
*/

Route::prefix('instructor')
    ->name('instructor.')
    ->group(static function (): void {
        // Phase 10.
    });
