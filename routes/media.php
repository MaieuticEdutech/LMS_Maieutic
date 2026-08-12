<?php

declare(strict_types=1);

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
        // Phase 6.
    });
