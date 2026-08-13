<?php

declare(strict_types=1);

use App\Http\Controllers\Media\MediaAccessController;
use App\Http\Controllers\Media\MediaStreamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Protected media delivery
|--------------------------------------------------------------------------
|
| Every byte of protected learning content — video, PDF, PPTX, downloadable
| resources — is delivered through this file. There is NO other path to it.
| The `content` disk is private, is not symlinked into public/, and has
| 'serve' => false precisely so Laravel's built-in local-disk serving route
| cannot bypass the checks below (config/filesystems.php).
|
| NON-NEGOTIABLE RULES (FR-FILE-06 … FR-FILE-09, NFR-SEC-22, AC-19, AC-20):
|   1. Authorise BEFORE issuing a URL. MediaFilePolicy resolves the owning
|      course and requires active enrollment, instructor assignment, or admin.
|   2. URLs are short lived — config('lms.media.url_ttl'), capped at 300s.
|      A URL that leaks stops working in minutes, not forever.
|   3. Video responses honour HTTP Range so seeking works (FR-FILE-08).
|   4. Downloads are served with Content-Disposition: attachment and
|      X-Content-Type-Options: nosniff, never an executable content type.
|   5. Rate limited via the `media` limiter (architecture.md §18.3).
|   6. Access is audit-logged, throttled to avoid flooding the log.
|
| Delivered by Phase 6.
|
*/

Route::prefix('media')
    ->name('media.')
    ->group(static function (): void {

        /*
        | STEP 1 — ask for a URL.
        |
        | Session-authenticated and active-account gated. A guest is redirected
        | by `auth` before the policy is ever consulted (AC-01); an
        | authenticated stranger is refused by the policy (AC-02).
        */
        Route::middleware(['auth', 'active', 'throttle:media'])
            ->get('/{media}/url', MediaAccessController::class)
            ->name('url');

        /*
        | STEP 2 — fetch the bytes (local disk only; S3 serves its own).
        |
        | `signed` proves we issued the URL and that it has not expired. It
        | does NOT prove the holder is still entitled — the controller re-runs
        | the policy for that, because an enrollment can be revoked between
        | minting a URL and using it.
        |
        | Deliberately NOT behind `auth`. A <video> element follows a signed
        | URL with the session cookie attached in the ordinary case, but the
        | signature plus the in-controller policy check is what actually
        | secures this route — and requiring the middleware too would break
        | nothing today while adding a second thing to keep in sync.
        */
        Route::middleware(['signed', 'throttle:media'])
            ->get('/{media}/stream', MediaStreamController::class)
            ->name('stream');
    });
