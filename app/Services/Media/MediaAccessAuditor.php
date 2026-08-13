<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Records who reached which protected file, without drowning the audit log.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * WHY THIS IS THROTTLED RATHER THAN A PLAIN AuditLogger CALL.
 *
 * A video player does not make one request per video. Seeking a 40-minute
 * lecture produces a Range request per scrub, and a flaky connection produces
 * a fresh one every few seconds. Logging each would write thousands of rows
 * for one student watching one file.
 *
 * That is not merely wasteful. `audit_logs` is append-only and legally
 * meaningful (NFR-SEC-17) — the record of who did what. Burying a genuine
 * "an administrator revoked this enrollment" entry under ten thousand
 * near-identical stream reads makes the log useless for the thing it exists
 * for. A log nobody can read is not an audit trail.
 *
 * So one entry per user, per file, per window. The evidence that a user
 * reached a file survives; the noise does not.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * The throttle NEVER gates access. If the cache is down, `Cache::add()` fails
 * open and we simply log — losing a deduplication guarantee is acceptable,
 * refusing a paying student their video because Redis blinked is not.
 */
final class MediaAccessAuditor
{
    /**
     * One entry per user+file+action within this window.
     *
     * Fifteen minutes is longer than almost any single viewing session, so a
     * normal watch produces one row. It is short enough that returning the
     * next morning is recorded as the separate visit it is.
     */
    private const THROTTLE_SECONDS = 900;

    public function __construct(private readonly AuditLogger $audit) {}

    public function record(?User $user, MediaFile $media, string $action): void
    {
        if (! $user instanceof User) {
            return;
        }

        if (! $this->shouldRecord($user, $media, $action)) {
            return;
        }

        $this->audit->record(
            action: $action,
            actor: $user,
            subject: $media,
            changes: ['after' => [
                'media_ulid' => $media->ulid,
                'purpose' => $media->purpose->value,
            ]],
            description: sprintf(
                'Accessed %s "%s".',
                $media->purpose->value,
                $media->original_name ?? $media->ulid,
            ),
        );
    }

    /**
     * `Cache::add()` is atomic — it writes only if the key is absent and tells
     * you which happened. Two simultaneous requests cannot both decide they
     * are the first, which a get-then-put would allow.
     */
    private function shouldRecord(User $user, MediaFile $media, string $action): bool
    {
        $key = sprintf('media-audit:%s:%s:%s', $user->getKey(), $media->getKey(), $action);

        try {
            return Cache::add($key, true, self::THROTTLE_SECONDS);
        } catch (Throwable) {
            // Cache unavailable. Log rather than skip: an over-full audit log
            // is recoverable, a missing entry is not.
            return true;
        }
    }
}
