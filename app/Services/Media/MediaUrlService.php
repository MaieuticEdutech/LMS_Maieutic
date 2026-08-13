<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\MediaFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Issues a short-lived URL for a protected file (architecture.md §16.1).
 *
 * TWO STRATEGIES, ONE INTERFACE:
 *
 *   Local disk → a Laravel signed route to MediaStreamController, which
 *                re-checks the policy and streams with HTTP Range support.
 *   S3         → a pre-signed object URL issued by the storage driver, so
 *                bytes never pass through PHP.
 *
 * Callers never learn which is in use. Switching is `LMS_CONTENT_DISK` in the
 * environment and nothing else (FR-FILE-10) — the whole reason the strategies
 * live behind one method instead of an `if` at each call site.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THIS CLASS DOES NOT AUTHORISE. It assumes the caller already did.
 *
 * That is a deliberate split, and the ordering is the security property:
 * authorise, THEN mint a URL. A service that checked permission itself invites
 * a caller to skip its own check and "let the URL service handle it" — and the
 * one path that forgets is the one that leaks. MediaFilePolicy is the only
 * authority; every caller here passes through it first (§16.2).
 *
 * The TTL is clamped, not trusted. NFR-SEC-22 caps it at 300 seconds, and
 * config already clamps it — clamping again here means a future caller that
 * passes its own TTL cannot widen the window past the cap by accident.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class MediaUrlService
{
    /**
     * NFR-SEC-22. A leaked URL must stop working in minutes, not never.
     */
    private const MAX_TTL_SECONDS = 300;

    /**
     * A URL the browser can use immediately, and cannot use for long.
     *
     * @param  int|null  $ttlSeconds  Overrides the configured TTL. Still clamped.
     */
    public function urlFor(MediaFile $file, ?int $ttlSeconds = null): string
    {
        $ttl = $this->resolveTtl($ttlSeconds);

        return $this->supportsTemporaryUrls($file->disk)
            ? $this->preSignedObjectUrl($file, $ttl)
            : $this->signedStreamRoute($file, $ttl);
    }

    /**
     * Seconds until the URL returned by `urlFor()` stops working.
     *
     * The player needs this to refresh a long video's URL before it expires
     * mid-playback — a five-minute URL and a forty-minute lecture otherwise
     * means the stream dies a third of the way in.
     */
    public function ttl(): int
    {
        return $this->resolveTtl(null);
    }

    /**
     * S3 and compatible drivers mint their own pre-signed URLs, so the bytes
     * are served by the object store rather than by PHP.
     *
     * Local disks cannot do this — `temporaryUrl()` throws on them — hence the
     * capability check rather than a hardcoded driver name. A future driver
     * that supports it works without a change here.
     */
    private function supportsTemporaryUrls(string $disk): bool
    {
        return Storage::disk($disk)->providesTemporaryUrls();
    }

    /**
     * Only reached when `supportsTemporaryUrls()` has already said yes, so
     * there is no second capability guard here — it would be unreachable, and
     * unreachable defensive code is a claim of safety nobody has tested.
     *
     * The safety that matters is upstream: `urlFor()` never falls back to a
     * permanent URL, so a misconfigured disk produces an error rather than an
     * unexpiring link to protected content.
     */
    private function preSignedObjectUrl(MediaFile $file, int $ttl): string
    {
        return Storage::disk($file->disk)->temporaryUrl($file->path, now()->addSeconds($ttl));
    }

    /**
     * A signed route to our own streaming controller.
     *
     * The signature makes the URL unforgeable and self-expiring; the
     * controller re-checks the policy anyway. Both matter: the signature stops
     * a stranger constructing a URL, and the policy re-check stops a student
     * whose enrollment was revoked one minute ago from using a URL minted two
     * minutes ago.
     *
     * A signed URL is a claim about who issued it, never a claim about who is
     * still entitled to use it.
     */
    private function signedStreamRoute(MediaFile $file, int $ttl): string
    {
        return URL::temporarySignedRoute(
            'media.stream',
            now()->addSeconds($ttl),
            ['media' => $file->getRouteKey()],
        );
    }

    private function resolveTtl(?int $requested): int
    {
        $configured = config()->integer('lms.media.url_ttl', self::MAX_TTL_SECONDS);
        $ttl = $requested ?? $configured;

        // Clamped at both ends: a zero or negative TTL would produce a URL
        // that is already expired, which reads as "media is broken" rather
        // than as the configuration mistake it is.
        return max(1, min($ttl, self::MAX_TTL_SECONDS));
    }

    /**
     * @return Filesystem the disk a file physically lives on
     */
    public function diskFor(MediaFile $file): Filesystem
    {
        return Storage::disk($file->disk);
    }
}
