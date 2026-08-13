<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Protected media delivery — rate limiting (architecture.md §18.3).
 *
 * Kept out of FortifyServiceProvider deliberately: that file owns
 * authentication limiters, and media delivery is a different concern with a
 * different failure mode. Bundling them would mean editing the auth provider
 * to tune video streaming.
 */
final class MediaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerMediaLimiter();
    }

    /**
     * ═════════════════════════════════════════════════════════════════════
     * WHAT THIS LIMITER IS FOR, AND WHAT IT IS NOT FOR.
     *
     * It is NOT the access control. A student without an enrollment is stopped
     * by MediaFilePolicy on request one, not on request 121. Rate limiting a
     * hole does not close it.
     *
     * It exists for the authorised-but-abusive case: an enrolled student
     * scripting a bulk download of every video in a course to redistribute it.
     * They have legitimate access to each file individually, so no policy can
     * refuse them — only the *rate* distinguishes watching a course from
     * scraping one.
     *
     * THE CEILING HAS TO CLEAR REAL PLAYBACK BY A WIDE MARGIN. A video element
     * issues a Range request per seek, and a student scrubbing through a
     * lecture looking for one section can fire dozens in a minute. Set this
     * too tight and the punishment lands on the most engaged learner — the one
     * re-watching a hard passage — while a patient scraper simply waits.
     *
     * 120/minute per user is roughly twice the busiest plausible viewing
     * session, and still far below what bulk extraction needs to be worthwhile.
     * ═════════════════════════════════════════════════════════════════════
     */
    private function registerMediaLimiter(): void
    {
        RateLimiter::for('media', static function (Request $request): Limit {
            $user = $request->user();

            // Keyed per user where possible: a shared IP is normal — a college
            // computer lab, an office, a household — and keying on it alone
            // would let one heavy viewer throttle everyone around them.
            return $user !== null
                ? Limit::perMinute(120)->by('media:user:'.$user->getAuthIdentifier())
                : Limit::perMinute(20)->by('media:ip:'.(string) $request->ip());
        });
    }
}
