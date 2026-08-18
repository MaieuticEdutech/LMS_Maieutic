<?php

declare(strict_types=1);

use App\Http\Controllers\CertificateDocumentController;
use App\Http\Controllers\CertificateDownloadController;
use App\Livewire\Student\AttemptHistory;
use App\Livewire\Student\AttemptResult;
use App\Livewire\Student\AttemptRunner;
use App\Livewire\Student\Certificates;
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

        /*
        | Phase 8 — assessment attempt runner and results.
        |
        | ONE CANONICAL URL PER ASSESSMENT for the runner itself: mount()
        | resumes an in-progress attempt or starts one via StartAttempt,
        | same "resolve or start" shape as the player above. Result and
        | history are bound by the ATTEMPT (ulid) and the ASSESSMENT
        | respectively — AttemptPolicy/EnrollmentAccessService do the actual
        | gating, this grouping is not the control (Rule 20).
        */
        Route::get('/assessments/{assessment}/attempt', AttemptRunner::class)->name('assessments.attempt');
        Route::get('/assessments/{assessment}/history', AttemptHistory::class)->name('assessments.history');
        Route::get('/attempts/{attempt}/result', AttemptResult::class)->name('assessments.result');

        /*
        | Certificates (design handoff §7).
        |
        | The LIST is student-only and self-scoped by its query. The single
        | certificate view is authorised per record by CertificatePolicy — a
        | super admin may open any, a student only their own.
        |
        | Note what is NOT here: the public verification route. It lives in
        | web.php with no auth at all, because a credential a stranger cannot
        | check is not a credential. See CertificatePolicy for why that is safe.
        */
        Route::get('/certificates', Certificates::class)->name('certificates.index');
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

    /*
    | The certificate DOCUMENT — the printable sheet, bound by `number`.
    |
    | Outside the student-only group above, and named without the `student.`
    | prefix, because it is not a student screen: CertificatePolicy::view lets a
    | super admin open any certificate, and `role:student` would lock them out
    | of a record they are explicitly authorised to see. The role middleware
    | says who an AREA is for; the policy says who a RECORD is for, and this
    | route is about a record (Rule 20).
    |
    | Not public, unlike /verify/{number}. That page answers "is this real?" for
    | a stranger; this one hands over a print-ready certificate, and those need
    | different doors — see CertificateDocumentController.
    */
    Route::get('/certificates/{certificate}', CertificateDocumentController::class)
        ->name('certificates.show');

    /*
    | The same certificate as a PDF file.
    |
    | Rate-limited because it is the one authenticated route here that does
    | real work per request — dompdf lays out the whole document each time.
    | Nothing is cached on disk deliberately (see the controller), so the
    | throttle is what stops a held-down key turning a download button into a
    | way to spend the server's CPU.
    */
    Route::get('/certificates/{certificate}/download', CertificateDownloadController::class)
        ->middleware('throttle:20,1')
        ->name('certificates.download');
});
