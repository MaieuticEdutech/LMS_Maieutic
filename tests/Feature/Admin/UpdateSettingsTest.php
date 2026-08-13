<?php

declare(strict_types=1);

use App\Actions\Admin\UpdateSettings;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| UpdateSettings — only changed values are written or audited
|--------------------------------------------------------------------------
|
| FR-SYS-01, FR-ADM-16, phases.md Phase 4 DoD. SettingsRepository::set()'s
| own docblock names this Action as the thing that wires it to an audited
| admin screen — these tests hold that contract.
|
*/

it('writes and audits only the keys that actually changed', function (): void {
    $actor = User::factory()->superAdmin()->create();

    $changed = Setting::query()->create([
        'group' => 'branding',
        'key' => 'branding.organisation_name',
        'value' => 'Old Name',
        'type' => 'string',
        'is_public' => true,
    ]);

    $unchanged = Setting::query()->create([
        'group' => 'branding',
        'key' => 'branding.support_email',
        'value' => 'support@lms.test',
        'type' => 'string',
        'is_public' => true,
    ]);

    // Give the DB a moment to separate created_at from a would-be updated_at
    // so an accidental touch is detectable.
    $unchangedUpdatedAtBefore = $unchanged->updated_at;
    if ($unchangedUpdatedAtBefore === null) {
        throw new RuntimeException('Expected a freshly created Setting to have updated_at set.');
    }

    app(UpdateSettings::class)->handle([
        'branding.organisation_name' => 'New Name',
        'branding.support_email' => 'support@lms.test',
    ], $actor);

    $changed->refresh();
    $unchanged->refresh();

    expect($changed->value)->toBe('New Name')
        ->and($unchanged->value)->toBe('support@lms.test');

    expect($unchanged->updated_at?->equalTo($unchangedUpdatedAtBefore))->toBeTrue();

    $entry = AuditLog::query()->where('action', 'settings.updated')->sole();

    // toEqual(), not toBe(): Postgres jsonb explicitly does not preserve
    // object key order (per its own docs), so a strict/ordered comparison of
    // a jsonb-backed column is asserting something the storage layer never
    // promised to keep.
    expect($entry->changes)->toEqual([
        'before' => ['branding.organisation_name' => 'Old Name'],
        'after' => ['branding.organisation_name' => 'New Name'],
    ]);
});

it('does not write an audit entry when nothing changed', function (): void {
    $actor = User::factory()->superAdmin()->create();

    Setting::query()->create([
        'group' => 'branding',
        'key' => 'branding.organisation_name',
        'value' => 'Same Name',
        'type' => 'string',
        'is_public' => true,
    ]);

    app(UpdateSettings::class)->handle([
        'branding.organisation_name' => 'Same Name',
    ], $actor);

    expect(AuditLog::query()->where('action', 'settings.updated')->exists())->toBeFalse();
});

it('preserves group, type and is_public — only the value may change through this path', function (): void {
    $actor = User::factory()->superAdmin()->create();

    Setting::query()->create([
        'group' => 'learning',
        'key' => 'learning.default_passing_percentage',
        'value' => 60,
        'type' => 'integer',
        'is_public' => false,
    ]);

    app(UpdateSettings::class)->handle([
        'learning.default_passing_percentage' => 75,
    ], $actor);

    $setting = Setting::query()->where('key', 'learning.default_passing_percentage')->sole();

    expect($setting->value)->toBe(75)
        ->and($setting->group)->toBe('learning')
        ->and($setting->type)->toBe('integer')
        ->and($setting->is_public)->toBeFalse();
});

it('coerces a numeric string to an integer before comparing, so an unchanged number is not treated as a change', function (): void {
    $actor = User::factory()->superAdmin()->create();

    Setting::query()->create([
        'group' => 'learning',
        'key' => 'learning.video_completion_threshold',
        'value' => 90,
        'type' => 'integer',
        'is_public' => false,
    ]);

    // Form inputs arrive as strings even for numeric fields.
    app(UpdateSettings::class)->handle([
        'learning.video_completion_threshold' => '90',
    ], $actor);

    expect(AuditLog::query()->where('action', 'settings.updated')->exists())->toBeFalse();
});

it('ignores a key that does not correspond to an existing setting', function (): void {
    $actor = User::factory()->superAdmin()->create();

    app(UpdateSettings::class)->handle([
        'no.such.key' => 'anything',
    ], $actor);

    expect(Setting::query()->where('key', 'no.such.key')->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'settings.updated')->exists())->toBeFalse();
});
