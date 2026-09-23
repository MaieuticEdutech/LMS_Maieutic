<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Malicious upload corpus — Phase 14, AC-21
|--------------------------------------------------------------------------
|
| tests/Feature/Catalogue/MediaUploadTest.php (Phase 5) already covers the
| core attack: a script renamed with a forged extension and Content-Type.
| This file is the Phase 14 audit pass — it targets the specific gaps that
| audit surfaced rather than repeating what's already proven:
|
|   1. The stored `mime_type` reflecting real detected content, not
|      whatever the client's Content-Type header claimed (the app trusts
|      this value again later — MediaStreamController serves it back as
|      the response's Content-Type header).
|   2. Disguised content under an ALLOWED extension (SVG/script bytes named
|      "cover.png"), not just a forbidden one — the forbidden-extension
|      deny-list can't catch this, only the content sniff can.
|   3. The deny-list additions this pass made (phar, jsp, asp, mjs, wasm,
|      etc.) actually reject, not just exist in the constant.
|
| KNOWN, ACCEPTED LIMITATION — not covered here, not silently ignored:
| the content sniff checks top-level magic bytes only. A zip-based format
| (docx/xlsx/pptx/zip) that passes its magic-byte check can still be a zip
| bomb or contain a webshell inside its archive — this validator does not
| decompress and inspect archive contents. Flagged in the Phase 14 audit
| as future work, not attempted in this pass.
*/

use App\Enums\MediaPurpose;
use App\Exceptions\MediaValidationException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\Module;
use App\Models\User;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('content');
    Storage::fake('public');

    $this->admin = User::factory()->superAdmin()->create();

    $course = Course::factory()->create();
    $module = Module::factory()->forCourse($course)->create();
    $this->lesson = Lesson::factory()->forModule($module)->create();
    $this->course = $course;

    $this->storage = app(MediaStorageService::class);
});

afterEach(function (): void {
    $directory = storage_path('framework/testing/uploads');

    if (is_dir($directory)) {
        foreach (glob($directory.DIRECTORY_SEPARATOR.'corpus_*') ?: [] as $file) {
            @unlink($file);
        }
    }
});

/**
 * Same technique as MediaUploadTest's upload() helper (real bytes on real
 * disk, so finfo sniffs genuine content) — reimplemented with a distinct
 * prefix and function name rather than shared, since Pest loads every test
 * file's global functions into one process and a second `upload()` would
 * collide with it.
 */
function uploadCorpusFixture(string $filename, string $bytes, ?string $claimedMime = null): UploadedFile
{
    $directory = storage_path('framework/testing/uploads');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $path = $directory.DIRECTORY_SEPARATOR.'corpus_'.bin2hex(random_bytes(8));
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $filename, $claimedMime, null, true);
}

function corpusPdfBytes(): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

function corpusPngBytes(): string
{
    return "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 1, 1)."\x08\x02\x00\x00\x00".pack('N', 0);
}

// Well-formed enough that finfo recognises it as XML/SVG rather than
// falling back to text/plain — either way, neither is image/png, which is
// the only signature 'png' accepts.
function corpusSvgWithScriptBytes(): string
{
    return <<<'SVG'
        <?xml version="1.0" encoding="UTF-8"?>
        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
            <script type="text/javascript">alert(document.cookie)</script>
        </svg>
        SVG;
}

/*
| ═══════════ 1. THE mime_type COLUMN REFLECTS REALITY, NOT THE CLIENT ═══════════
*/
it('stores the sniffed mime type, ignoring a forged client Content-Type', function (): void {
    // The file genuinely IS a pdf; only the claimed Content-Type header
    // lies. A valid file with a lying header is a realistic case — a proxy,
    // extension, or the attacker's own client can set this header to
    // anything, independent of whether the upload is otherwise legitimate.
    $media = $this->storage->store(
        uploadCorpusFixture('report.pdf', corpusPdfBytes(), 'application/x-something-else'),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    );

    expect($media->mime_type)->toBe('application/pdf')
        ->not->toBe('application/x-something-else');
});

/*
| ═══════════ 2. DISGUISED CONTENT UNDER AN ALLOWED EXTENSION ═══════════
*/
it('rejects SVG/script content disguised with an allowed image extension', function (): void {
    // "vector.svg" is already forbidden outright — that's not the gap.
    // The gap this closes: the SAME bytes, renamed to an extension that
    // IS on the allow-list. Only the content sniff can catch this, because
    // the extension itself passes every earlier check.
    expect(fn () => $this->storage->store(
        uploadCorpusFixture('cover.png', corpusSvgWithScriptBytes(), 'image/png'),
        $this->course,
        MediaPurpose::Thumbnail,
        $this->admin,
    ))->toThrow(MediaValidationException::class);

    expect(MediaFile::query()->count())->toBe(0);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

/*
| ═══════════ 3. THIS PASS'S DENY-LIST ADDITIONS ACTUALLY REJECT ═══════════
*/
it('rejects the extensions newly added to the forbidden list', function (string $filename): void {
    expect(fn () => $this->storage->store(
        uploadCorpusFixture($filename, corpusPdfBytes(), 'application/pdf'),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    ))->toThrow(MediaValidationException::class);

    expect(MediaFile::query()->count())->toBe(0);
})->with([
    'backdoor.phar',
    'shell.jsp',
    'shell.asp',
    'shell.aspx',
    'script.cgi',
    'script.pl',
    'script.py',
    'worker.mjs',
    'module.wasm',
    'compressed.svgz',
]);

/*
| ═══════════ 4. A GENUINELY OVERSIZED FILE, WITH REAL BYTES ═══════════
*/
it('rejects a file whose real size exceeds the configured limit for its purpose', function (): void {
    config()->set('lms.media.max_bytes.thumbnail', 100);

    // Padded well past the limit with real bytes, not a truncated header —
    // the size check runs before the content sniff, so this never reaches
    // finfo at all.
    $oversized = corpusPngBytes().str_repeat("\x00", 1024);

    expect(fn () => $this->storage->store(
        uploadCorpusFixture('cover.png', $oversized, 'image/png'),
        $this->course,
        MediaPurpose::Thumbnail,
        $this->admin,
    ))->toThrow(MediaValidationException::class);

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});
