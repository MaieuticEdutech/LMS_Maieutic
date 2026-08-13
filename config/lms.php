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
    | Bootstrap Super Admin
    |--------------------------------------------------------------------------
    |
    | Identity of the first administrator, created by SuperAdminSeeder.
    |
    | Read through config() rather than env() at the call site (rule E-3): a
    | seeder runs through artisan, and once `config:cache` has been run in a
    | deployed environment env() returns NULL — which would silently seed an
    | administrator with an empty email. This is not a style preference; it is
    | the difference between a working bootstrap and a broken one in
    | production.
    |
    */

    'super_admin' => [
        'email' => env('LMS_SUPER_ADMIN_EMAIL', 'admin@lms.test'),
        'name' => env('LMS_SUPER_ADMIN_NAME', 'Super Admin'),
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

    /*
    |--------------------------------------------------------------------------
    | Queues (Phase 11, architecture.md §13)
    |--------------------------------------------------------------------------
    |
    | Four named queues, in priority order. Workers drain them left to right,
    | so a backlog of report exports can never delay a payment webhook or an
    | activation email.
    |
    |   critical — payments, enrollment. Money and access.
    |   mail     — every outbound email.
    |   default  — progress, audit.
    |   low      — media cleanup, exports.
    |
    | Queue names are referenced through this config, never hardcoded in a job
    | class, so renaming a queue is a config change and a job cannot be
    | silently orphaned onto a queue no worker is listening to.
    |
    */

    'queues' => [
        'critical' => 'critical',
        'mail' => 'mail',
        'default' => 'default',
        'low' => 'low',

        /*
         * The worker's drain order, and the single source of truth for it.
         * Deployment (Phase 16) builds the supervisor `--queue=` argument from
         * this list rather than repeating it in a process manager config where
         * it would drift.
         */
        'priority' => ['critical', 'mail', 'default', 'low'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail (Phase 11, architecture.md §14)
    |--------------------------------------------------------------------------
    |
    | TRANSPORT-AGNOSTIC BY CONSTRUCTION (PD-07). Nothing here — and nothing in
    | any mailable — names a delivery provider. Development uses log/Mailpit and
    | production selects a provider in Phase 16 by setting MAIL_MAILER, with no
    | application code change (FR-MAIL-09).
    |
    | Organisation identity (name, logo, support address, footer) is NOT here.
    | It lives in `settings` behind BrandingService — see the header of this
    | file and rule S-1.
    |
    */

    'mail' => [
        // SendMailJob retry policy (architecture.md §13: "3, 60s").
        'tries' => (int) env('LMS_MAIL_TRIES', 3),
        'backoff' => [60, 300, 900],

        /*
         * Hard ceiling on one send attempt. Must stay well below the queue
         * connection's retry_after (90s) or a slow SMTP handshake would let
         * the job be dispatched to a second worker while still running, and
         * the recipient would get the email twice.
         */
        'timeout' => (int) env('LMS_MAIL_TIMEOUT', 30),

        /*
         * Whether mail previews are reachable. Development only — the routes
         * render real templates with real branding and must never be exposed
         * in production, where they would leak organisation configuration.
         */
        'preview_enabled' => (bool) env('LMS_MAIL_PREVIEW_ENABLED', false),
    ],

];
