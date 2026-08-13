<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InstructorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstructorProfile>
 *
 * Kept out of `users` so the identity table stays role-neutral
 * (architecture.md §6.4), which is also why the profile needs a factory of its
 * own rather than being a state on UserFactory.
 */
class InstructorProfileFactory extends Factory
{
    protected $model = InstructorProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // An instructor, not a bare user: a profile hanging off a student
            // is not a state the application can reach, and a factory that
            // produced one would make tests pass against impossible data.
            'user_id' => User::factory()->instructor(),
            'headline' => fake()->jobTitle(),
            'bio' => fake()->paragraph(),
            'expertise' => [fake()->word(), fake()->word()],
            'links' => ['website' => fake()->url()],
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->getKey()]);
    }

    /**
     * The state a freshly created instructor is in before anyone fills the
     * profile out — every optional column empty.
     */
    public function bare(): static
    {
        return $this->state(fn (): array => [
            'headline' => null,
            'bio' => null,
            'expertise' => null,
            'links' => null,
        ]);
    }
}
