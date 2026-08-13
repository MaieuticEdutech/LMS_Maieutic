<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Services\Media\MediaAccessAuditor;
use App\Services\Media\MediaUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issues a short-lived URL for a protected file (architecture.md §16.2).
 *
 * The player asks here first; it never guesses a storage path. That is the
 * whole point of the indirection — the browser learns a URL that works for
 * five minutes, and never learns where the file actually lives.
 *
 * ORDER IS THE SECURITY PROPERTY:
 *   1. authorise      — MediaFilePolicy, which resolves the owning course and
 *                       asks EnrollmentAccessService
 *   2. mint the URL   — only after the check passes
 *   3. audit          — throttled, so a 40-minute video cannot flood the log
 *
 * Reversing 1 and 2 would mean a URL exists, however briefly, for a user who
 * was never entitled to it.
 *
 * AC-01: a guest reaching this route is redirected by the `auth` middleware.
 * AC-02: an authenticated but unenrolled student gets 403 from the policy.
 * Neither depends on the player hiding a button (Rule 20).
 */
final class MediaAccessController extends Controller
{
    public function __construct(
        private readonly MediaUrlService $urls,
        private readonly MediaAccessAuditor $auditor,
    ) {}

    public function __invoke(Request $request, MediaFile $media): JsonResponse
    {
        // Throws 403 before any URL exists.
        $this->authorize('access', $media);

        $this->auditor->record($request->user(), $media, 'media.url_issued');

        return response()->json([
            'url' => $this->urls->urlFor($media),
            // The player refreshes before this elapses. A 40-minute lecture
            // behind a 5-minute URL would otherwise die mid-playback.
            'expires_in' => $this->urls->ttl(),
            'is_downloadable' => $media->is_downloadable,
        ]);
    }
}
