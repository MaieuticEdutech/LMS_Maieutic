<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Student Area — ENROLLED CONTENT
|--------------------------------------------------------------------------
|
| Every route in this file MUST sit behind:
|     ->middleware(['auth', 'active', 'role:student'])
|
| ACCESS RULE (FR-RBAC-05, FR-ENR-01, AC-02):
| Authentication alone grants NOTHING. A student account is not course access.
| Every route that exposes course content must additionally pass the
| enrollment gate — resolved in exactly one place,
| EnrollmentAccessService::grantsAccess(), which requires an enrollment with
| status active|completed that has not expired (architecture.md §8.5, §12.2,
| rule S-8). No route may re-implement that check locally.
|
| Delivered by:
|   Phase 7  — dashboard, My Courses, course player, profile
|   Phase 8  — assessment attempt runner and results
|   Phase 9  — progress and "continue learning"
|   Phase 12 — checkout, payment status, payment history
|
*/

Route::name('student.')
    ->group(static function (): void {
        // Phase 7 onwards.
    });
