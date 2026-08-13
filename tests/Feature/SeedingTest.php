<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\BrandingService;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Phase 3 DoD · "migrate:fresh --seed succeeds and yields realistic data"
|--------------------------------------------------------------------------
|
| The seeders had one incidental reference in the whole suite and nothing ran
| DatabaseSeeder. That is a gap with real consequences in both directions:
|
|   A BROKEN SEEDER breaks onboarding. It is the first command a new developer
|   runs, and Phase 16 runs it against a fresh production database — where the
|   super admin it creates is the only way in.
|
|   A SEEDER THAT RUNS TWICE must not duplicate. planning.md §8 rule 13 says
|   every seeder is idempotent, and a deploy that re-seeds is ordinary.
|
| The environment guard is the security-relevant part: the development
| accounts all share a known password, and the ONLY thing keeping them out of
| production is `app()->environment('local')`.
|
*/

it('seeds a fresh database without error', function (): void {
    expect(Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]))->toBe(0);
});

it('creates the super admin that is the only way into a fresh installation', function (): void {
    Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);

    $admin = User::query()->where('role', UserRole::SuperAdmin)->first();

    expect($admin)->not->toBeNull()
        ->and($admin?->status->value)->toBe('active');
});

it('seeds the branding settings that every email and page reads', function (): void {
    Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);

    // Settings are seeded FIRST deliberately, so BrandingService has values
    // before anything renders or sends. An unseeded organisation name would
    // surface as a blank in the mail header rather than as an error.
    app(SettingsRepository::class)->flush();

    expect(app(BrandingService::class)->organisationName())->not->toBeEmpty()
        ->and(Setting::query()->count())->toBeGreaterThan(0);
});

it('does not duplicate anything when run a second time', function (): void {
    Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);

    $usersAfterFirst = User::query()->count();
    $settingsAfterFirst = Setting::query()->count();

    Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);

    // A re-seed is an ordinary part of a deploy. Idempotence is what makes it
    // safe (planning.md §8 rule 13).
    expect(User::query()->count())->toBe($usersAfterFirst)
        ->and(Setting::query()->count())->toBe($settingsAfterFirst);
});

it('creates no known-password account outside local', function (): void {
    /*
     * ═════════════════════════════════════════════════════════════════════
     * THE ONE ASSERTION HERE THAT IS A SECURITY CONTROL.
     *
     * The development accounts share the password "password". What keeps them
     * out of production is a single environment check inside DatabaseSeeder —
     * no configuration, no flag an operator sets. If that check were ever
     * loosened, a production seed would create five logins with a password
     * anyone reading this repository knows.
     *
     * The suite runs as `testing`, so this asserts the non-local path.
     * ═════════════════════════════════════════════════════════════════════
     */
    expect(app()->environment('local'))->toBeFalse();

    Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);

    $devAccounts = User::query()
        ->whereIn('email', [
            'instructor@lms.test',
            'student@lms.test',
            'unverified@lms.test',
            'awaiting@lms.test',
            'suspended@lms.test',
        ])
        ->count();

    expect($devAccounts)->toBe(0);
});

it('leaves a seeded installation able to serve its public pages', function (): void {
    Artisan::call('db:seed', ['--class' => DatabaseSeeder::class]);
    app(SettingsRepository::class)->flush();

    // "Realistic data" in the DoD means the installation works, not that rows
    // exist. The catalogue is the first thing a visitor reaches.
    $this->get('/')->assertSuccessful();
    $this->get(route('catalogue.index'))->assertSuccessful();
});
