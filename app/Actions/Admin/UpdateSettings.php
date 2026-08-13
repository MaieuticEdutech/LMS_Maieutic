<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsRepository;

/**
 * Bulk-applies value changes from the admin settings screen (FR-SYS-01,
 * FR-ADM-16, phases.md Phase 4).
 *
 * ONLY THE VALUE MAY CHANGE HERE. A setting's `group`, `type` and
 * `is_public` are read back from its existing row and passed straight
 * through to SettingsRepository::set() unchanged — the caller supplies a
 * new value, never a new shape. This is what SettingsRepository::set()'s
 * own docblock means by "callers must authorise first — this is a plain
 * writer, not a policy enforcement point": SettingPolicy is the gate,
 * SettingsForm calls it, and this Action is the one place the two meet.
 *
 * Only keys whose value actually changed are written or audited — an
 * untouched setting's `updated_at` must not move, and a form submission
 * that changes nothing must not create an audit entry.
 */
final class UpdateSettings
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $values  key => new value, for every setting on the submitted form.
     */
    public function handle(array $values, User $actor): void
    {
        $before = [];
        $after = [];

        foreach ($values as $key => $rawValue) {
            $setting = Setting::query()->where('key', $key)->first();

            if ($setting === null) {
                continue;
            }

            $newValue = $this->coerce($rawValue, $setting->type);

            if ($setting->value === $newValue) {
                continue;
            }

            $before[$key] = $setting->value;
            $after[$key] = $newValue;

            $this->settings->set($key, $newValue, $setting->group, $setting->type, $setting->is_public);
        }

        if ($before === []) {
            return;
        }

        $this->audit->record(
            action: 'settings.updated',
            actor: $actor,
            subject: null,
            changes: ['before' => $before, 'after' => $after],
            description: "Settings updated by {$actor->email}.",
        );
    }

    /**
     * Form input arrives as scalars from HTML inputs (strings for text and
     * number fields). Coerce to the setting's real type before comparing or
     * storing, so "90" (string) is never treated as a change from 90 (int).
     */
    private function coerce(mixed $rawValue, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $rawValue,
            // Mirrors SettingsRepository::boolean(): a real bool passes through,
            // anything else (a stray "0"/"false" string) is parsed rather than
            // truthy-cast, which is what makes (bool) "false" === true wrong.
            'boolean' => is_bool($rawValue) ? $rawValue : filter_var($rawValue, FILTER_VALIDATE_BOOLEAN),
            default => is_scalar($rawValue) ? (string) $rawValue : $rawValue,
        };
    }
}
