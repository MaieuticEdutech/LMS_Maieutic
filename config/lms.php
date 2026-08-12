<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LMS application configuration
|--------------------------------------------------------------------------
|
| Infrastructure-level and environment-specific configuration for the LMS.
|
| IMPORTANT (planning.md rule E-8 / S-1):
| ORGANISATION-level settings — organisation name, logo, support email, and
| any value an administrator should be able to change at runtime — do NOT
| belong here. They live in the `settings` database table and are read through
| SettingsRepository / BrandingService. That separation is the seam that makes
| future multi-organisation support a configuration change rather than a
| rewrite (architecture.md §24.2).
|
| Only infrastructure credentials, driver selection and environment endpoints
| belong in this file. env() is called here and nowhere else (rule E-3).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Storage disks
    |--------------------------------------------------------------------------
    |
    | `content` holds protected learning material (video, PDF, PPTX, resources).
    | It MUST always resolve to a private disk that the web server does not
    | serve directly (FR-FILE-03, NFR-SEC-12). Switching local -> s3 is a
    | configuration change only; no application code changes (FR-FILE-10).
    |
    */

    'disks' => [
        'content' => env('LMS_CONTENT_DISK', 'content'),
        'public' => 'public',
        'temp' => 'temp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Media delivery
    |--------------------------------------------------------------------------
    |
    | Signed URLs for protected media are deliberately short lived. NFR-SEC-22
    | caps this at 300 seconds; the value is clamped rather than trusted so a
    | mistaken environment value cannot silently weaken content protection.
    |
    */

    'media' => [
        'url_ttl' => min((int) env('LMS_MEDIA_URL_TTL', 300), 300),

        // Upload ceilings in bytes, per purpose (PD-05 proposed defaults).
        // These move to the `settings` table in Phase 5 so an administrator can
        // change them without a deployment.
        'max_bytes' => [
            'video' => 2 * 1024 * 1024 * 1024,   // 2 GB
            'document' => 50 * 1024 * 1024,      // 50 MB
            'presentation' => 100 * 1024 * 1024, // 100 MB
            'attachment' => 100 * 1024 * 1024,   // 100 MB
            'thumbnail' => 5 * 1024 * 1024,      // 5 MB
        ],

        // Server-side allow-lists. Client-reported MIME is never trusted;
        // content is sniffed on upload (FR-FILE-04).
        'allowed_extensions' => [
            'video' => ['mp4', 'webm', 'mov'],
            'document' => ['pdf'],
            'presentation' => ['ppt', 'pptx', 'odp'],
            'attachment' => ['pdf', 'zip', 'docx', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg'],
            'thumbnail' => ['png', 'jpg', 'jpeg', 'webp'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication lifetimes
    |--------------------------------------------------------------------------
    |
    | Activation links are longer lived than password resets: a buyer may not
    | open their email immediately after purchase (FR-AUTH-06, FR-MAIL-01).
    | Both are single-use and stored hashed by the password broker (ADR-004).
    |
    */

    'auth' => [
        'activation_link_ttl' => (int) env('LMS_ACTIVATION_LINK_TTL', 4320), // minutes (72h)
        'password_reset_ttl' => (int) env('LMS_PASSWORD_RESET_TTL', 60),     // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Progress tracking
    |--------------------------------------------------------------------------
    |
    | Defaults only. The authoritative, administrator-editable values move to
    | the `settings` table in Phase 9 (FR-PROG-03, FR-ADM-16).
    |
    */

    'progress' => [
        'video_completion_threshold' => (int) env('LMS_VIDEO_COMPLETION_THRESHOLD', 90), // percent
        'write_throttle_seconds' => 15, // FR-PROG-02
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | Phase 12. Credentials come from the environment only and are never
    | committed or logged (FR-PAY-15, NFR-SEC-14). All amounts are integers in
    | the currency's minor unit — paise (FR-CRS-09, ADR-007).
    |
    */

    'payments' => [
        'gateway' => 'razorpay',
        'currency' => 'INR',

        'razorpay' => [
            'key_id' => env('RAZORPAY_KEY_ID'),
            'key_secret' => env('RAZORPAY_KEY_SECRET'),
            'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        ],
    ],

];
