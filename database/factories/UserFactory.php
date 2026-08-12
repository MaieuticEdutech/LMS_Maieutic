<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Default state: an ACTIVE STUDENT.
     *
     * Chosen deliberately as the default because it is the most common subject
     * of a test. States below cover the other roles and every lifecycle stage,
     * so a test reads as `User::factory()->suspended()->create()` and the
     * intent is obvious at the call site.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Student,
            'status' => UserStatus::Active,
            'phone' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Role states
    |--------------------------------------------------------------------------
    */

    public function superAdmin(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::SuperAdmin]);
    }

    public function instructor(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Instructor]);
    }

    public function student(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Student]);
    }

    /*
    |--------------------------------------------------------------------------
    | Status states
    |--------------------------------------------------------------------------
    */

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::Inactive]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::Suspended]);
    }

    /**
     * Self-registered but not yet verified: has a password, cannot log in.
     */
    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::PendingVerification,
            'email_verified_at' => null,
        ]);
    }

    /**
     * Created on the user's behalf — as a verified purchase will do in Phase 12.
     *
     * `password` is NULL, which is the security property that matters: such an
     * account cannot satisfy Hash::check() under any input, so it is
     * structurally unable to authenticate until activated (architecture.md §6.4).
     */
    public function awaitingActivation(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::PendingActivation,
            'password' => null,
            'email_verified_at' => null,
            'remember_token' => null,
        ]);
    }
}
