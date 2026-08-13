<?php

declare(strict_types=1);

namespace App\Services\Progress;

use App\Services\Settings\SettingsRepository;

/**
 * The two numbers that govern progress, read from settings (FR-PROG-03,
 * FR-ADM-16).
 *
 * Config holds the DEFAULTS; the settings table holds what an administrator
 * has actually chosen. Reading them through one class means the player, the
 * action and the rebuild command cannot disagree about the threshold — which
 * they would the first time someone tuned it in only one place.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * BOTH VALUES ARE CLAMPED, NOT TRUSTED.
 *
 * A threshold of 0 would complete every video the instant it opened. A
 * threshold above 100 could never be reached, leaving every video lesson
 * permanently incomplete and every course stuck at 90-something percent with
 * no way for a student to finish.
 *
 * Neither is a hypothetical: both are one mistyped digit in an admin form.
 * The clamp means the worst case is a threshold that is not quite what was
 * intended, rather than a product that silently cannot be completed.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class ProgressSettings
{
    /** Below this, "watched" stops being meaningful evidence. */
    private const MIN_THRESHOLD = 50;

    private const MAX_THRESHOLD = 100;

    /** A throttle looser than this loses too much of a short lesson. */
    private const MAX_THROTTLE = 60;

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Percentage of a video that counts as watched (FR-PROG-04).
     *
     * Deliberately below 100 by default: credits, outros and a student closing
     * the tab a few seconds early are all normal, and demanding the final
     * frame would leave lessons stuck at 99%.
     */
    public function videoCompletionThreshold(): int
    {
        $configured = $this->settings->integer(
            'progress.video_completion_threshold',
            config()->integer('lms.progress.video_completion_threshold', 90),
        );

        return max(self::MIN_THRESHOLD, min($configured, self::MAX_THRESHOLD));
    }

    /**
     * Minimum seconds between two progress writes for one lesson (FR-PROG-02).
     *
     * A video reports its position several times a second. Without this, one
     * student watching one lesson is thousands of writes for a resume point
     * accurate to the same few seconds either way.
     */
    public function writeThrottleSeconds(): int
    {
        $configured = $this->settings->integer(
            'progress.write_throttle_seconds',
            config()->integer('lms.progress.write_throttle_seconds', 15),
        );

        // Zero is allowed and means "every write lands" — useful in tests and
        // for a deliberately chatty debug session.
        return max(0, min($configured, self::MAX_THROTTLE));
    }
}
