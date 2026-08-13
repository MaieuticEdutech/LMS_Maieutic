<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Services\Media\MediaAccessAuditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the bytes of a protected file from a LOCAL disk (FR-FILE-08).
 *
 * Reached only through a signed URL minted by MediaUrlService. On S3 this
 * controller is never used at all — the object store serves the bytes
 * directly from a pre-signed URL.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THE SIGNATURE IS NOT THE AUTHORISATION.
 *
 * `signed` middleware proves the URL was issued by us and has not expired. It
 * says nothing about whether the holder is still entitled to the content. A
 * student whose enrollment was revoked sixty seconds ago still holds a URL
 * that was validly signed two minutes ago.
 *
 * So the policy runs again here, on every request. That is not belt and
 * braces — it is the only check that reflects the present. Revocation must be
 * immediate (FR-ENR-08), and a URL cannot be recalled once handed out.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * RANGE SUPPORT is what makes video seeking work. A browser asks for
 * `Range: bytes=5000000-` when the user drags the scrubber; answering 200 with
 * the whole file means the player must download from the start every time, and
 * seeking a long lecture becomes unusable.
 */
final class MediaStreamController extends Controller
{
    public function __construct(private readonly MediaAccessAuditor $auditor) {}

    public function __invoke(Request $request, MediaFile $media): StreamedResponse
    {
        // Re-checked despite the valid signature. See the class docblock.
        $this->authorize('stream', $media);

        $disk = Storage::disk($media->disk);

        abort_unless($disk->exists($media->path), 404);

        $this->auditor->record($request->user(), $media, 'media.streamed');

        $size = (int) $disk->size($media->path);
        $range = $this->parseRange($request->header('Range'), $size);

        return $range === null
            ? $this->fullResponse($media, $size)
            : $this->partialResponse($media, $size, $range[0], $range[1]);
    }

    /**
     * Parse a single-range `Range: bytes=start-end` header.
     *
     * Returns null when there is no header, when it is malformed, or when it
     * asks for something other than a single byte range — in every one of
     * those cases serving the whole file is a correct response, and is far
     * better than failing.
     *
     * Multi-range requests (`bytes=0-99,200-299`) are deliberately not
     * supported: they require multipart/byteranges, no browser video element
     * issues them, and an unused code path handling untrusted input is a
     * liability rather than a feature.
     *
     * @return array{0: int, 1: int}|null [start, end] inclusive
     */
    private function parseRange(?string $header, int $size): ?array
    {
        if ($header === null || $size <= 0) {
            return null;
        }

        if (preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $m) !== 1) {
            return null;
        }

        [, $rawStart, $rawEnd] = $m;

        if ($rawStart === '' && $rawEnd === '') {
            return null;
        }

        if ($rawStart === '') {
            // Suffix form: "bytes=-500" means the LAST 500 bytes. Players use
            // this to read a trailing MP4 moov atom before anything else.
            $length = (int) $rawEnd;

            if ($length <= 0) {
                return null;
            }

            $start = max(0, $size - $length);
            $end = $size - 1;
        } else {
            $start = (int) $rawStart;
            $end = $rawEnd === '' ? $size - 1 : (int) $rawEnd;
        }

        // Clamp rather than trust: a request beyond EOF must not read past the
        // end of the file.
        $end = min($end, $size - 1);

        if ($start > $end || $start >= $size) {
            return null;
        }

        return [$start, $end];
    }

    private function fullResponse(MediaFile $media, int $size): StreamedResponse
    {
        return response()->stream(
            function () use ($media): void {
                $stream = Storage::disk($media->disk)->readStream($media->path);

                if ($stream !== null) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            200,
            $this->headers($media, $size) + ['Content-Length' => (string) $size],
        );
    }

    private function partialResponse(MediaFile $media, int $size, int $start, int $end): StreamedResponse
    {
        $length = $end - $start + 1;

        return response()->stream(
            function () use ($media, $start, $length): void {
                $stream = Storage::disk($media->disk)->readStream($media->path);

                if ($stream === null) {
                    return;
                }

                fseek($stream, $start);

                // Copied in chunks rather than read whole: a 2 GB video read
                // into a string would exhaust memory long before it reached
                // the client.
                $remaining = $length;
                $chunkSize = 1024 * 512;

                while ($remaining > 0 && ! feof($stream)) {
                    $buffer = fread($stream, (int) min($chunkSize, $remaining));

                    if ($buffer === false) {
                        break;
                    }

                    echo $buffer;
                    flush();

                    $remaining -= strlen($buffer);
                }

                fclose($stream);
            },
            206,
            $this->headers($media, $size) + [
                'Content-Length' => (string) $length,
                'Content-Range' => sprintf('bytes %d-%d/%d', $start, $end, $size),
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function headers(MediaFile $media, int $size): array
    {
        $headers = [
            'Content-Type' => $media->mime_type ?? 'application/octet-stream',
            // Tells the player it may seek at all. Without it, browsers assume
            // the whole file must be downloaded first.
            'Accept-Ranges' => 'bytes',
            // Never let a browser second-guess the declared type — that is how
            // a file gets executed as something it is not.
            'X-Content-Type-Options' => 'nosniff',
            // Protected content must not sit in a shared or CDN cache. `private`
            // alone is not enough when the URL is short-lived by design.
            'Cache-Control' => 'private, no-store, max-age=0',
        ];

        // Video and audio stream inline. Everything else downloads as an
        // attachment, with the original filename restored for the user —
        // the stored name is a ULID and means nothing to them.
        $headers['Content-Disposition'] = $media->isStreamed()
            ? 'inline'
            : 'attachment; filename="'.addslashes($media->original_name ?? 'download').'"';

        return $headers;
    }
}
