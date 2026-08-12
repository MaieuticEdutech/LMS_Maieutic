<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Settings\SettingsRepository;
use Illuminate\Database\Seeder;

/**
 * Seeds organisation-level settings (FR-SYS-01, FR-ADM-16).
 *
 * These are the values that must NEVER be hardcoded in a view, an email
 * template or a config file — they are what becomes per-organisation in V2
 * (planning.md rule S-1, architecture.md §24.2).
 *
 * Idempotent: uses updateOrCreate, so re-running never duplicates a key and
 * never clobbers a value an administrator has since changed... except for the
 * defaults below, which are only written when the key is absent.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Branding — consumed by BrandingService in every layout and email.
            ['branding.organisation_name', 'LMS', 'branding', 'string', true],
            ['branding.support_email', 'support@lms.test', 'branding', 'string', true],
            ['branding.logo_path', '', 'branding', 'string', true],
            ['branding.mail_from_address', 'no-reply@lms.test', 'branding', 'string', false],
            ['branding.mail_from_name', 'LMS', 'branding', 'string', false],
            ['branding.email_footer', '', 'branding', 'string', true],

            // Learning defaults. Authoritative from Phase 9 (FR-PROG-03);
            // seeded now so the key exists and the settings screen has
            // something to show in Phase 4.
            ['learning.video_completion_threshold', 90, 'learning', 'integer', false],
            ['learning.default_passing_percentage', 60, 'learning', 'integer', false],
        ];

        foreach ($defaults as [$key, $value, $group, $type, $isPublic]) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'group' => $group,
                    'type' => $type,
                    'is_public' => $isPublic,
                ],
            );
        }

        app(SettingsRepository::class)->flush();
    }
}
