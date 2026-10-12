<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Models\Order;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * THE ONE SWITCH for the free-launch promotion (planning.md §16.4).
 *
 * While active, students may enrol for free and the purchase UI is hidden.
 * Nothing of the paid path (checkout, Razorpay, webhooks, orders) is removed or
 * disabled; when the date passes or the setting is cleared, every purchase
 * surface returns with no deploy.
 *
 * Every view and action asks here. Nobody reads the setting directly.
 */
final class Promotion
{
    public const SETTING = 'promotion.free_access_until';

    /** The admin enters the end date in the organisation's local time. */
    public const TIMEZONE = 'Asia/Kolkata';

    public function __construct(private readonly SettingsRepository $settings) {}

    public function active(): bool
    {
        $ends = $this->endsAt();

        return $ends !== null && $ends->isFuture();
    }

    /**
     * The "Payment history" menu item is noise while everything is free, but a
     * student who already paid for something must still be able to find it.
     */
    public function showsPaymentHistoryTo(User $user): bool
    {
        return ! $this->active() || Order::query()->where('user_id', $user->getKey())->exists();
    }

    /**
     * When the promotion ends, or null when none is configured (or the value
     * cannot be read, which fails closed: no promotion).
     */
    public function endsAt(): ?CarbonImmutable
    {
        $raw = trim($this->settings->string(self::SETTING));

        if ($raw === '') {
            return null;
        }

        try {
            $ends = CarbonImmutable::parse($raw, self::TIMEZONE);
        } catch (Throwable) {
            return null;
        }

        // A bare date means "through the end of that day".
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1 ? $ends->endOfDay() : $ends;
    }
}
