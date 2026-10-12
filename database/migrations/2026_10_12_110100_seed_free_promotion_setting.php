<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/*
|--------------------------------------------------------------------------
| settings — promotion.free_access_until
|--------------------------------------------------------------------------
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED (settings).
|
| Existing deployments never re-run SettingsSeeder, so the key is inserted
| here. Empty means no promotion is running. Only inserted when absent, so
| re-running can never overwrite a date an administrator has set.
*/
return new class extends Migration
{
    public function up(): void
    {
        Setting::query()->firstOrCreate(
            ['key' => 'promotion.free_access_until'],
            ['value' => '', 'group' => 'promotion', 'type' => 'string', 'is_public' => true],
        );
    }

    public function down(): void
    {
        Setting::query()->where('key', 'promotion.free_access_until')->delete();
    }
};
