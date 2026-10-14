<?php

declare(strict_types=1);

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

/*
|--------------------------------------------------------------------------
| The profile_documents disk can move to a bucket by configuration alone
|--------------------------------------------------------------------------
|
| Learner photos and Aadhaar scans were hard-wired to the server's disk, so a
| redeploy or a lost disk took student documents with it. LMS_PROFILE_
| DOCUMENTS_DRIVER=s3 moves them to a private bucket. The disk NAME does not
| change, so ProfileDocumentStorage and its tests are untouched.
|
| config/filesystems.php is evaluated at boot, so each case sets the env var,
| re-reads the file, and restores the environment afterwards.
*/

/**
 * @param  array<string, string>  $env
 * @return array<string, mixed>
 */
function profileDocumentsDisk(array $env): array
{
    $repository = Env::getRepository();
    $keys = array_keys($env);

    foreach ($env as $key => $value) {
        $repository->set($key, $value);
    }

    try {
        $config = require config_path('filesystems.php');
    } finally {
        foreach ($keys as $key) {
            $repository->clear($key);
        }
    }

    return $config['disks']['profile_documents'];
}

it('stays on the local disk by default', function (): void {
    $disk = profileDocumentsDisk([]);

    expect($disk['driver'])->toBe('local')
        ->and($disk['root'])->toBe(storage_path('app/profile-documents'))
        ->and($disk['serve'])->toBeFalse()
        ->and($disk['visibility'])->toBe('private');
});

it('switches to a private S3 bucket under its own prefix', function (): void {
    $disk = profileDocumentsDisk([
        'LMS_PROFILE_DOCUMENTS_DRIVER' => 's3',
        'AWS_BUCKET' => 'lms-content',
        'AWS_DEFAULT_REGION' => 'ap-south-1',
        'AWS_ACCESS_KEY_ID' => 'test-key',
        'AWS_SECRET_ACCESS_KEY' => 'test-secret',
    ]);

    expect($disk['driver'])->toBe('s3')
        ->and($disk['bucket'])->toBe('lms-content')
        ->and($disk['root'])->toBe('profile-documents')
        ->and($disk['visibility'])->toBe('private')
        ->and($disk['throw'])->toBeTrue()
        // Never web-served and never given a public URL.
        ->and($disk)->not->toHaveKey('url');
});

it('lets the profile bucket differ from the content bucket', function (): void {
    $disk = profileDocumentsDisk([
        'LMS_PROFILE_DOCUMENTS_DRIVER' => 's3',
        'AWS_BUCKET' => 'lms-content',
        'LMS_PROFILE_DOCUMENTS_BUCKET' => 'lms-student-private',
    ]);

    expect($disk['bucket'])->toBe('lms-student-private');
});

it('builds a real adapter for each driver', function (): void {
    $s3 = Storage::build(profileDocumentsDisk([
        'LMS_PROFILE_DOCUMENTS_DRIVER' => 's3',
        'AWS_BUCKET' => 'lms-content',
        'AWS_DEFAULT_REGION' => 'ap-south-1',
        'AWS_ACCESS_KEY_ID' => 'test-key',
        'AWS_SECRET_ACCESS_KEY' => 'test-secret',
    ]));
    $local = Storage::build(profileDocumentsDisk([]));

    expect($s3->getAdapter())->toBeInstanceOf(AwsS3V3Adapter::class)
        ->and($local->getAdapter())->toBeInstanceOf(LocalFilesystemAdapter::class);
});
