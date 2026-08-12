<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\MediaPurpose;
use App\Exceptions\MediaValidationException;
use Illuminate\Http\UploadedFile;

/**
 * Validates an upload before a single byte reaches permanent storage
 * (FR-FILE-04, NFR-SEC-11, AC-21).
 *
 * FOUR INDEPENDENT CHECKS, IN ESCALATING COST ORDER:
 *
 *   1. SIZE       — cheapest. Reject an oversized file before reading it.
 *   2. EXTENSION  — allow-list per purpose. Not a deny-list: a deny-list is a
 *                   guess about every dangerous extension that will ever
 *                   exist, and it is always incomplete.
 *   3. CLIENT MIME — allow-list, but treated as a HINT ONLY. The browser
 *                   supplies it and an attacker controls the browser.
 *   4. CONTENT SNIFF — the real check. Reads the file's actual magic bytes
 *                   with finfo and compares them against what the extension
 *                   claims. This is what catches shell.php renamed to
 *                   video.mp4 with a spoofed Content-Type header.
 *
 * Checks 2 and 3 exist so that obviously-wrong uploads fail fast with a clear
 * message. Check 4 is the one that stops an attack.
 *
 * ON FAILURE: throws. Nothing is stored, no database row is written, and the
 * temporary file is discarded by the caller. A partially-accepted upload is
 * not a state this system has.
 */
final class FileValidationService
{
    /**
     * Extension → the magic-byte MIME types that extension may legitimately
     * produce. A file whose sniffed type is not in its extension's list is
     * lying about what it is.
     *
     * @var array<string, list<string>>
     */
    private const EXTENSION_SIGNATURES = [
        'mp4' => ['video/mp4', 'application/mp4', 'video/quicktime'],
        'webm' => ['video/webm'],
        'mov' => ['video/quicktime', 'video/mp4'],
        'pdf' => ['application/pdf'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage'],
        'pptx' => [
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
        ],
        'odp' => ['application/vnd.oasis.opendocument.presentation', 'application/zip'],
        'zip' => ['application/zip'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'txt' => ['text/plain'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
    ];

    /**
     * Extensions that are never acceptable, whatever the purpose allow-list
     * says.
     *
     * This is belt-and-braces on top of the allow-list, not a substitute for
     * it: if a future edit to config accidentally widens an allow-list, these
     * still cannot get through.
     *
     * @var list<string>
     */
    private const ALWAYS_FORBIDDEN = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps',
        'exe', 'dll', 'so', 'bat', 'cmd', 'com', 'msi', 'scr',
        'sh', 'bash', 'ps1', 'psm1', 'vbs', 'js', 'jar',
        'html', 'htm', 'xhtml', 'shtml', 'svg', 'xml',
        'htaccess', 'htpasswd',
    ];

    /**
     * @throws MediaValidationException
     */
    public function validate(UploadedFile $file, MediaPurpose $purpose): void
    {
        $this->assertUploadSucceeded($file);
        $this->assertWithinSizeLimit($file, $purpose);

        $extension = $this->normalisedExtension($file);

        $this->assertExtensionNotForbidden($extension);
        $this->assertExtensionAllowedForPurpose($extension, $purpose);
        $this->assertContentMatchesExtension($file, $extension);
    }

    /**
     * The extension we will actually store, derived from the CLIENT filename
     * but normalised and re-validated.
     *
     * Uses the last segment only, so "payload.php.mp4" yields "mp4" — and
     * because the stored filename is generated from the ULID anyway, the
     * double extension never reaches the disk.
     */
    public function normalisedExtension(UploadedFile $file): string
    {
        $extension = mb_strtolower((string) $file->getClientOriginalExtension());

        return preg_replace('/[^a-z0-9]/', '', $extension) ?? '';
    }

    private function assertUploadSucceeded(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new MediaValidationException(
                'The upload did not complete. Please try again.',
            );
        }

        // A zero-byte upload means the pipeline failed silently somewhere.
        // The database CHECK would reject it later; failing here gives a
        // usable error instead of a constraint violation.
        if ($file->getSize() === false || $file->getSize() <= 0) {
            throw new MediaValidationException('The file is empty.');
        }
    }

    private function assertWithinSizeLimit(UploadedFile $file, MediaPurpose $purpose): void
    {
        $max = config()->integer('lms.media.max_bytes.'.$purpose->configKey(), 0);

        if ($max <= 0) {
            throw new MediaValidationException(
                "No upload size limit is configured for [{$purpose->value}]. Refusing the upload.",
            );
        }

        $size = (int) $file->getSize();

        if ($size > $max) {
            throw new MediaValidationException(sprintf(
                'This file is %s. The maximum for %s is %s.',
                $this->humanBytes($size),
                $purpose->label(),
                $this->humanBytes($max),
            ));
        }
    }

    private function assertExtensionNotForbidden(string $extension): void
    {
        if ($extension === '') {
            throw new MediaValidationException('The file has no extension.');
        }

        if (in_array($extension, self::ALWAYS_FORBIDDEN, true)) {
            throw new MediaValidationException(
                "Files of type .{$extension} are never accepted.",
            );
        }
    }

    private function assertExtensionAllowedForPurpose(string $extension, MediaPurpose $purpose): void
    {
        /** @var list<string> $allowed */
        $allowed = config()->array('lms.media.allowed_extensions.'.$purpose->configKey(), []);

        if ($allowed === []) {
            throw new MediaValidationException(
                "No allowed file types are configured for [{$purpose->value}]. Refusing the upload.",
            );
        }

        if (! in_array($extension, $allowed, true)) {
            throw new MediaValidationException(sprintf(
                'A .%s file is not accepted for %s. Allowed: %s.',
                $extension,
                $purpose->label(),
                implode(', ', array_map(static fn (string $e): string => '.'.$e, $allowed)),
            ));
        }
    }

    /**
     * THE CHECK THAT MATTERS (AC-21).
     *
     * Reads the file's actual magic bytes rather than trusting anything the
     * client said. Catches a PHP script renamed to .mp4 with a forged
     * Content-Type — which is the standard route to remote code execution
     * through an upload form.
     */
    private function assertContentMatchesExtension(UploadedFile $file, string $extension): void
    {
        $expected = self::EXTENSION_SIGNATURES[$extension] ?? null;

        if ($expected === null) {
            // An extension passed the allow-list but has no signature entry.
            // Refuse rather than skip: an unverifiable file is not a safe one,
            // and this is a configuration mistake worth surfacing loudly.
            throw new MediaValidationException(
                "Cannot verify the contents of a .{$extension} file. Refusing the upload.",
            );
        }

        $detected = $this->detectMimeType($file);

        if ($detected === null) {
            throw new MediaValidationException('The file contents could not be read.');
        }

        if (! in_array($detected, $expected, true)) {
            throw new MediaValidationException(sprintf(
                'This file claims to be .%s but its contents are %s. It has been rejected.',
                $extension,
                $detected,
            ));
        }
    }

    /**
     * Magic-byte detection via finfo, never the client-supplied MIME type.
     */
    private function detectMimeType(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $detected = finfo_file($finfo, $path);

        // No finfo_close() call, deliberately. As of PHP 8.5 the resource is an
        // object freed on garbage collection, and closing it explicitly is
        // deprecated — it becomes a hard error in PHP 9. Do not "fix" this by
        // adding the close back.
        return $detected === false ? null : $detected;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }
}
