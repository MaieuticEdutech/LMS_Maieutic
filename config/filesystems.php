<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            // (string) cast is a real invariant, not a silencer: env() has a
            // bool|string|null union return type, but APP_URL is by definition
            // a URL string. Without it the whole project cannot run static
            // analysis above level 7.
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * PROTECTED LEARNING CONTENT — architecture.md §15.1, FR-FILE-03.
         *
         * Videos, PDFs, presentations and downloadable resources live here.
         * This disk is NEVER symlinked into public/ and NEVER served directly
         * by the web server. Every byte is delivered through an authorised
         * controller or a short-lived signed URL after a policy check
         * (FR-FILE-06, FR-FILE-07).
         *
         * 'serve' is deliberately FALSE: Laravel's built-in local-disk serving
         * route would bypass our MediaFilePolicy entirely.
         *
         * Production sets LMS_CONTENT_DISK=s3_content to switch to a private
         * bucket with no code change (FR-FILE-10).
         */
        'content' => [
            'driver' => 'local',
            'root' => storage_path('app/content'),
            'serve' => false,
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        /*
         * S3-compatible equivalent of the `content` disk, for production.
         * Same contract, different driver — selected by LMS_CONTENT_DISK.
         */
        's3_content' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        /*
         * Scratch space for in-flight uploads and generated exports.
         * Never publicly served; contents are transient and prunable.
         */
        'temp' => [
            'driver' => 'local',
            'root' => storage_path('app/temp'),
            'serve' => false,
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
