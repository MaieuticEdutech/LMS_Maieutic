<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Exceptions\MediaValidationException;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\Module;
use App\Models\User;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Phase 5 · Secure upload pipeline (AC-20, AC-21, FR-FILE-01…14)
|--------------------------------------------------------------------------
|
| These use files with REAL MAGIC BYTES rather than UploadedFile::fake(),
| because the check that actually stops an attack is the magic-byte content
| sniff. A fake file full of zeros would never exercise it, and the suite
| would pass while proving nothing.
|
*/

beforeEach(function (): void {
    Storage::fake('content');
    Storage::fake('public');

    $this->admin = User::factory()->superAdmin()->create();

    // Held as typed locals so tests never walk a nullable relationship chain.
    $this->course = Course::factory()->create();
    $module = Module::factory()->forCourse($this->course)->create();
    $this->lesson = Lesson::factory()->forModule($module)->create(['type' => LessonType::Document]);

    $this->storage = app(MediaStorageService::class);
});

afterEach(function (): void {
    // Leave nothing behind. These are real files on a real disk — the whole
    // point of the helper below — so they need real cleanup.
    $directory = storage_path('framework/testing/uploads');

    if (is_dir($directory)) {
        foreach (glob($directory.DIRECTORY_SEPARATOR.'upload_*') ?: [] as $file) {
            @unlink($file);
        }
    }
});

/**
 * Write real bytes to a temp file and wrap it as an upload.
 *
 * The point is that finfo sees genuine content, so the sniff is real.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * WHY NOT sys_get_temp_dir(): this originally wrote into the system temp
 * directory, and failed on two of three Windows machines while passing on the
 * third and in CI. On Windows, %TEMP% is a busy shared directory that
 * real-time antivirus scans on write — and a scanner holding a brief lock is
 * enough for the finfo_file() read that follows to fail. Nothing in the test
 * holds a handle open; file_put_contents() closes what it opens.
 *
 * Writing inside the project's own storage removes the shared directory, and
 * with it the interference. A test that passes or fails depending on which
 * machine runs it is worse than no test, because it teaches the team to
 * ignore a red suite.
 * ─────────────────────────────────────────────────────────────────────────
 *
 * The directory is emptied after every test, so a run leaves nothing behind.
 */
function upload(string $filename, string $bytes, ?string $claimedMime = null): UploadedFile
{
    $directory = storage_path('framework/testing/uploads');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    // Unique per call, so parallel or repeated cases never collide.
    $path = $directory.DIRECTORY_SEPARATOR.'upload_'.bin2hex(random_bytes(8));
    file_put_contents($path, $bytes);

    return new UploadedFile(
        $path,
        $filename,
        $claimedMime,       // what the CLIENT claims — attacker-controlled
        null,
        true,               // test mode
    );
}

function pdfBytes(): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

function pngBytes(): string
{
    return "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 1, 1)."\x08\x02\x00\x00\x00".pack('N', 0);
}

function phpBytes(): string
{
    return "<?php system(\$_GET['cmd']); ?>\n";
}

/*
| HAPPY PATH — and where the bytes land (AC-20).
*/
it('stores a valid pdf on the private disk with a generated name', function (): void {
    $media = $this->storage->store(
        upload('notes.pdf', pdfBytes(), 'application/pdf'),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    );

    expect($media->disk)->toBe('content')
        // The stored filename is the ULID, never the user's (FR-FILE-05).
        ->and($media->path)->toContain($media->ulid)
        ->and($media->path)->not->toContain('notes')
        ->and($media->original_name)->toBe('notes.pdf')
        ->and($media->checksum_sha256)->not->toBeNull();

    Storage::disk('content')->assertExists($media->path);

    // Never on the public disk.
    Storage::disk('public')->assertMissing($media->path);

    expect(AuditLog::query()->where('action', 'media.uploaded')->exists())->toBeTrue();
});

it('puts thumbnails on the public disk — the only public media', function (): void {
    $media = $this->storage->store(
        upload('cover.png', pngBytes(), 'image/png'),
        $this->course,
        MediaPurpose::Thumbnail,
        $this->admin,
    );

    expect($media->disk)->toBe('public');
    Storage::disk('public')->assertExists($media->path);
});

/*
| ═══════════════ AC-21: REJECT AND STORE NOTHING ═══════════════
*/
it('rejects a php script renamed to .pdf with a forged mime type', function (): void {
    // The canonical upload attack: right extension, right Content-Type,
    // wrong contents. Only the magic-byte sniff catches this.
    expect(fn () => $this->storage->store(
        upload('invoice.pdf', phpBytes(), 'application/pdf'),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    ))->toThrow(MediaValidationException::class);

    // NOTHING was stored, and no record was written (AC-21).
    expect(MediaFile::query()->count())->toBe(0);
    expect(Storage::disk('content')->allFiles())->toBeEmpty();
});

it('rejects a forbidden extension outright', function (string $filename): void {
    expect(fn () => $this->storage->store(
        upload($filename, phpBytes(), 'application/pdf'),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    ))->toThrow(MediaValidationException::class);

    expect(MediaFile::query()->count())->toBe(0);
    expect(Storage::disk('content')->allFiles())->toBeEmpty();
})->with(['shell.php', 'run.exe', 'script.sh', 'page.html', 'vector.svg', 'app.js']);

it('reduces a double extension to its last segment and rejects it', function (): void {
    // "payload.php.pdf" is a .pdf by extension, but its CONTENTS are PHP,
    // so the sniff rejects it.
    expect(fn () => $this->storage->store(
        upload('payload.php.pdf', phpBytes(), 'application/pdf'),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    ))->toThrow(MediaValidationException::class);

    expect(Storage::disk('content')->allFiles())->toBeEmpty();
});

it('rejects an extension not allowed for the purpose', function (): void {
    // A PNG is a legitimate file, but not a legitimate DOCUMENT.
    expect(fn () => $this->storage->store(
        upload('image.png', pngBytes(), 'image/png'),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    ))->toThrow(MediaValidationException::class);

    expect(Storage::disk('content')->allFiles())->toBeEmpty();
});

it('rejects an oversized file before storing it', function (): void {
    config()->set('lms.media.max_bytes.document', 10);

    expect(fn () => $this->storage->store(
        upload('big.pdf', pdfBytes(), 'application/pdf'),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    ))->toThrow(MediaValidationException::class);

    expect(Storage::disk('content')->allFiles())->toBeEmpty();
});

it('rejects an empty file', function (): void {
    expect(fn () => $this->storage->store(
        upload('empty.pdf', '', 'application/pdf'),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    ))->toThrow(MediaValidationException::class);
});

it('rejects a file with no extension', function (): void {
    expect(fn () => $this->storage->store(
        upload('README', pdfBytes(), 'application/pdf'),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    ))->toThrow(MediaValidationException::class);
});

/*
| Video is streamed, never downloaded (FR-FILE-09).
*/
it('forces is_downloadable false for video whatever the caller asks', function (): void {
    $mp4 = "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41\x00\x00\x00\x08free";

    $media = $this->storage->store(
        upload('lesson.mp4', $mp4, 'video/mp4'),
        $this->lesson,
        MediaPurpose::Video,
        $this->admin,
        downloadable: true,   // caller asks for downloadable...
    );

    // ...and is overruled. Enforced server-side, not by hiding a button.
    expect($media->is_downloadable)->toBeFalse();
});

/*
| Path shape and the multi-tenancy seam (rule S-2).
*/
it('nests lesson media under its course so a course delete removes the tree', function (): void {
    $media = $this->storage->store(
        upload('notes.pdf', pdfBytes(), 'application/pdf'),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    );

    $courseId = $this->course->id;

    expect($media->path)
        ->toStartWith("courses/{$courseId}/lessons/{$this->lesson->id}/document/")
        ->toEndWith('.pdf');
});

it('sanitises the original filename kept as metadata', function (): void {
    $media = $this->storage->store(
        upload('<script>x</script>.pdf', pdfBytes(), 'application/pdf'),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    );

    // Displayed in the admin UI, so it must not carry markup.
    expect($media->original_name)->not->toContain('<script>');
});

/*
| Deletion.
*/
it('removes the file and the record together', function (): void {
    $media = $this->storage->store(
        upload('notes.pdf', pdfBytes(), 'application/pdf'),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    );
    $path = $media->path;

    $this->storage->delete($media, $this->admin);

    Storage::disk('content')->assertMissing($path);
    expect(MediaFile::query()->count())->toBe(0);
    expect(AuditLog::query()->where('action', 'media.deleted')->exists())->toBeTrue();
});
