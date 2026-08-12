<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| settings — organisation-level configuration
|--------------------------------------------------------------------------
|
| PHASE 2. architecture.md §6.4. FR-SYS-01, FR-ADM-16.
|
| TABLE CLASSIFICATION (rule S-5): TENANT-OWNED.
| `key` is COMPOSITE-READY: in V2 the unique index becomes
| (organisation_id, key) so each organisation carries its own branding,
| thresholds and support address (architecture.md §24.2).
|
| WHY THIS TABLE EXISTS AT ALL, IN PHASE 2, BEFORE ANYTHING NEEDS IT:
|
| This is the single most important multi-tenancy seam in the system
| (planning.md rule S-1). Organisation identity — name, logo, support email —
| must never be hardcoded in a view, an email template or a config file. If it
| were, adding a second organisation in V2 would mean hunting every hardcoded
| string through the entire codebase.
|
| Creating it now, and routing every reference through SettingsRepository and
| BrandingService from the very first email, means V2 changes one resolver
| rather than a hundred call sites. It costs almost nothing today and saves
| the rewrite the customer explicitly asked us to avoid.
|
| DISTINCT FROM .env (rule E-8): .env holds infrastructure credentials and
| environment endpoints. This table holds values an ADMINISTRATOR may change
| at runtime without a deployment.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // Grouping for the Phase 4 settings UI, e.g. branding, learning, mail.
            $table->string('group')->default('general');

            // Dot-notated, e.g. branding.organisation_name. Composite-ready.
            $table->string('key')->unique();

            // JSONB so a setting can hold a scalar, a list or a structure
            // without a schema change.
            $table->jsonb('value')->nullable();

            // Drives casting on read and the input widget in the admin UI.
            $table->string('type')->default('string');

            // Whether the value may be exposed to unauthenticated visitors
            // (organisation name: yes; SMTP details: never).
            $table->boolean('is_public')->default(false);

            $table->timestamps();

            $table->index(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
