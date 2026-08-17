<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

/*
|--------------------------------------------------------------------------
| Livewire's upload ceiling must not be smaller than the application's
|--------------------------------------------------------------------------
|
| THE BUG THIS EXISTS FOR.
|
| Livewire validates every temporary upload with `max:12288` (12 MB) BEFORE
| the application sees the file. `lms.media.max_bytes.video` allows 2 GB, so a
| 334 MB lecture was refused at the framework boundary with the app's own
| ceiling never consulted — and the error surfaced as a generic validation
| failure, giving no hint that two different limits were involved.
|
| Two limits that must agree, in two files, is exactly the arrangement that
| drifts. This test is the thing that notices.
|
| NOTE ON WHY config/livewire.php READS env() DIRECTLY: config files are
| evaluated while the config repository is still being assembled, so calling
| config('lms.media.max_bytes') from inside another config file returns the
| default rather than the real value — it silently reinstated the 12 MB cap.
| Hence the duplicated value, and hence this test.
|
*/

it('allows Livewire uploads at least as large as the biggest purpose the app accepts', function (): void {
    $rules = config()->array('livewire.temporary_file_upload.rules');

    $maxRule = Arr::first($rules, static fn (string $rule): bool => str_starts_with($rule, 'max:'));

    expect($maxRule)->not->toBeNull('Livewire must declare an explicit max: rule, or it falls back to 12 MB.');

    $livewireBytes = ((int) str_replace('max:', '', (string) $maxRule)) * 1024;

    /*
     * Folded rather than max()'d: PHPStan cannot know the config array is
     * non-empty, and an empty one would make max() throw. Starting from 0
     * means an empty config fails the comparison below rather than the test
     * itself — a clearer failure either way.
     */
    $appCeiling = array_reduce(
        config()->array('lms.media.max_bytes'),
        static fn (int $carry, mixed $bytes): int => max($carry, (int) $bytes),
        0,
    );

    expect($appCeiling)->toBeGreaterThan(0, 'lms.media.max_bytes must define at least one ceiling.');

    expect($livewireBytes)->toBeGreaterThanOrEqual($appCeiling,
        'Livewire would reject files the application is configured to accept. '
        .'Raise LIVEWIRE_MAX_UPLOAD_KB in config/livewire.php to match lms.media.max_bytes.',
    );
});

it('accepts a video the size of a real lecture', function (): void {
    // The actual file that surfaced this: a 334 MB MP4.
    $rules = config()->array('livewire.temporary_file_upload.rules');
    $maxRule = Arr::first($rules, static fn (string $rule): bool => str_starts_with($rule, 'max:'));
    $livewireKb = (int) str_replace('max:', '', (string) $maxRule);

    expect($livewireKb)->toBeGreaterThan(334 * 1024);
});

it('gives a long enough window to finish a large upload', function (): void {
    /*
     * 334 MB at a typical 2 Mbps upstream is over 20 minutes. The 5-minute
     * default invalidates the upload mid-flight, and the user sees the
     * transfer reach 100% and then fail — the most confusing failure of all.
     */
    expect(config()->integer('livewire.temporary_file_upload.max_upload_time'))
        ->toBeGreaterThanOrEqual(30);
});

it('still enforces the per-purpose ceiling in the application', function (): void {
    /*
     * Raising Livewire's limit must not become a way around the real one.
     * FileValidationService checks size, extension and magic bytes per
     * purpose after the upload lands (AC-21) — a 2 GB "thumbnail" is still
     * refused, because thumbnails cap at 5 MB.
     */
    $byPurpose = config()->array('lms.media.max_bytes');

    expect($byPurpose['thumbnail'])->toBeLessThan($byPurpose['video'])
        ->and($byPurpose['document'])->toBeLessThan($byPurpose['video']);
});
