<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Entry point for `php artisan db:seed` and `migrate:fresh --seed`.
 *
 * Order matters: settings are seeded first so BrandingService has values
 * before anything renders or sends an email.
 *
 * Every seeder here is idempotent — re-running produces the same result and
 * never duplicates a record (planning.md §8 rule 13).
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            SuperAdminSeeder::class,
        ]);

        // Development-only convenience accounts. Never created outside local,
        // so a production seed can never introduce a known-password login.
        if (app()->environment('local')) {
            $this->seedDevelopmentUsers();
        }
    }

    /**
     * One account per role and per interesting lifecycle state, so every
     * authentication path can be exercised by hand in development.
     *
     * All use the password "password" — acceptable ONLY because this method
     * never runs outside the local environment.
     */
    private function seedDevelopmentUsers(): void
    {
        User::factory()->instructor()->create([
            'name' => 'Dev Instructor',
            'email' => 'instructor@lms.test',
        ]);

        User::factory()->student()->create([
            'name' => 'Dev Student',
            'email' => 'student@lms.test',
        ]);

        // Registered but has not clicked the verification link: cannot log in.
        User::factory()->student()->unverified()->create([
            'name' => 'Unverified Student',
            'email' => 'unverified@lms.test',
        ]);

        // Simulates a Phase 12 purchase-created account: NULL password, so it
        // is structurally unable to authenticate until activated.
        User::factory()->student()->awaitingActivation()->create([
            'name' => 'Awaiting Activation',
            'email' => 'awaiting@lms.test',
        ]);

        User::factory()->student()->suspended()->create([
            'name' => 'Suspended Student',
            'email' => 'suspended@lms.test',
        ]);
    }
}
