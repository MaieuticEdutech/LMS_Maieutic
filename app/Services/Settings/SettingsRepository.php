<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Reads and writes organisation-level settings (FR-SYS-01, FR-ADM-16).
 *
 * THIS IS A MULTI-TENANCY SEAM (planning.md rule S-1, architecture.md §24.2).
 *
 * Every organisation-specific value in the system is read through here. No
 * view, email template or service may hardcode an organisation's name, logo,
 * support address or thresholds. In V2 this class gains an organisation scope
 * and every existing call site keeps working unchanged — which is the whole
 * reason it exists in Phase 2, long before there is an admin UI to edit
 * settings.
 *
 * DISTINCT FROM config() (rule E-8): config() holds infrastructure and
 * environment values that change per DEPLOYMENT. This holds values an
 * ADMINISTRATOR changes at RUNTIME.
 *
 * Settings are cached because they are read on nearly every request (branding
 * appears in every layout). The cache is flushed on write, and is always
 * rebuildable from the table — it is never the source of truth.
 */
final class SettingsRepository
{
    private const CACHE_KEY = 'lms.settings';

    private const CACHE_TTL = 3600;

    /**
     * In-request memoisation. Avoids repeated cache round-trips when several
     * components each ask for branding while rendering one page.
     *
     * @var array<string, mixed>|null
     */
    private ?array $memo = null;

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        /** @var array<string, mixed> $settings */
        $settings = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            static fn (): array => Setting::query()
                ->get(['key', 'value'])
                ->mapWithKeys(static fn (Setting $setting): array => [$setting->key => $setting->value])
                ->all(),
        );

        return $this->memo = $settings;
    }

    /**
     * Create or update a setting and invalidate the cache.
     *
     * Callers must authorise first — this is a plain writer, not a policy
     * enforcement point (Phase 4 wires it to the admin settings screen with
     * an audit entry).
     */
    public function set(string $key, mixed $value, string $group = 'general', string $type = 'string', bool $isPublic = false): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'type' => $type,
                'is_public' => $isPublic,
            ],
        );

        $this->flush();
    }

    public function flush(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }
}
