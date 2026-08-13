<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 *
 * ═════════════════════════════════════════════════════════════════════════
 * USE SettingsRepository::set() IN TESTS, NOT THIS FACTORY.
 *
 * The repository caches, and a row written straight to the table leaves that
 * cache stale — the test then asserts against a value the application will not
 * read. MailBrandingTest's beforeEach says the same thing and goes through the
 * repository for exactly this reason.
 *
 * This factory exists because Phase 3's DoD asks for one on every model, and
 * because a test about the TABLE — its unique key, its JSONB round-tripping —
 * is legitimately about the row rather than the value. Anything about
 * behaviour should go through the repository.
 * ═════════════════════════════════════════════════════════════════════════
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group' => 'general',
            // Unique, because `key` carries a unique index and a factory that
            // collided on a second call would fail confusingly.
            'key' => 'test.'.fake()->unique()->word(),
            'value' => fake()->word(),
            'type' => 'string',
            'is_public' => false,
        ];
    }

    public function inGroup(string $group): static
    {
        return $this->state(fn (): array => ['group' => $group]);
    }

    /**
     * Readable without authentication — organisation name, support email and
     * the like, which the public catalogue renders.
     */
    public function public(): static
    {
        return $this->state(fn (): array => ['is_public' => true]);
    }

    public function withValue(string $key, mixed $value, string $type = 'string'): static
    {
        return $this->state(fn (): array => [
            'key' => $key,
            'value' => $value,
            'type' => $type,
        ]);
    }
}
