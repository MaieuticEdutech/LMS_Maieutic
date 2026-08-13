<?php

declare(strict_types=1);

use App\Livewire\Student\CoursePlayer;
use App\Livewire\Student\Dashboard;
use App\Livewire\Student\MyCourses;
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
    ->middleware(['auth', 'active', 'role:student'])
    ->group(static function (): void {
        Route::get('/dashboard', Dashboard::class)->name('home');
        Route::get('/my-courses', MyCourses::class)->name('courses.index');

        /*
        | THE PLAYER — the only route that exposes lesson content.
        |
        | `role:student` above is NOT the access control. It establishes who
        | this area is for; CoursePlayer::mount() then authorises
        | `access` on the course, which resolves through
        | EnrollmentAccessService — the single definition (rule S-8).
        |
        | The lesson segment is optional. Without it the player resumes where
        | the student left off; with it, it opens that specific lesson. A
        | lesson that is not part of the published curriculum 404s rather
        | than 403s: it is not hidden from this student, it is not in the
        | course.
        |
        | Bound by slug (courses) and id (lessons) per each model's
        | getRouteKeyName.
        */
        Route::get('/learn/{course}/{lesson?}', CoursePlayer::class)->name('courses.play');
    });

/*
| Profile — available to EVERY authenticated role, so it sits outside the
| student-only group above. Users manage their own name, phone and password
| here (FR-STU-14).
|
| No `role` middleware: a super admin and an instructor need a profile too.
| Authorisation is per-record via UserPolicy::update, which allows a user to
| update themselves and a super admin to update anyone.
*/
Route::middleware(['auth', 'active'])->group(static function (): void {
    Route::view('/profile', 'profile.show')->name('profile.show');
});
