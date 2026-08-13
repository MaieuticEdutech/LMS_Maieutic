<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 *
 * Phase 3's DoD asks for a factory on every model, and this one was missing:
 * audit entries are written by AuditLogger, so nothing had needed to fabricate
 * one. Phase 13's reporting will, and a test that wants a populated log should
 * not have to drive a real action to get one.
 *
 * The model refuses updates and deletes (append-only, NFR-SEC-17), so a
 * factory can create but tests must never `->update()` what it made.
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Default state: a successful login by some user — the commonest entry in
     * a real log by a wide margin.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => 'auth.login.succeeded',
            'auditable_type' => null,
            'auditable_id' => null,
            'description' => 'Signed in.',
            'changes' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * An entry with no actor, as a scheduled command or a webhook produces.
     */
    public function bySystem(): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'action' => 'enrollment.expired',
            'description' => 'Access period ended.',
        ]);
    }

    public function forAction(string $action): static
    {
        return $this->state(fn (): array => ['action' => $action]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function withChanges(array $changes): static
    {
        return $this->state(fn (): array => ['changes' => $changes]);
    }
}
