<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| SettingPolicy — settings are super-admin only (FR-SYS-01, FR-ADM-16)
|--------------------------------------------------------------------------
|
| Unlike UserPolicy there is no "own record" exception: a setting belongs to
| the organisation, not to any individual user, so every non-super-admin is
| denied outright.
|
*/

it('lets only a super admin view and update settings', function (string $state, bool $allowed): void {
    $actor = User::factory()->{$state}()->create();
    $setting = Setting::query()->create([
        'group' => 'branding',
        'key' => 'branding.organisation_name',
        'value' => 'LMS',
        'type' => 'string',
        'is_public' => true,
    ]);

    expect($actor->can('viewAny', Setting::class))->toBe($allowed)
        ->and($actor->can('update', $setting))->toBe($allowed);
})->with([
    'super admin' => ['superAdmin', true],
    'instructor' => ['instructor', false],
    'student' => ['student', false],
]);
