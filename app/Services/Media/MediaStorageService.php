<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\MediaPurpose;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The secure upload pipeline (FR-FILE-01…14, architecture.md §15.3).
 *
 * ORDER OF OPERATIONS IS THE SECURITY PROPERTY:
 *
 *   validate → generate ULID → resolve path → write to PRIVATE disk →
 *   checksum → database row → audit
 *
 * Validation happens BEFORE anything is written, so a rejected file never
 * exists on disk even momentarily. The database row is written LAST, so a
 * failed write can never leave a `media_files` record pointing at nothing.
 *
 * And if the database write fails after the file landed, the file is deleted
 * again — no orphan is created by our own failure path. Orphans that arise
 * some other way are swept by DeleteOrphanedMedia.
 *
 * PATHS COME ONLY FROM MediaPathResolver (rule S-2). This class never
 * concatenates a path itself, which is what keeps the V2 tenant prefix a
 * one-line change.
 *
 * DISK SELECTION IS NOT A PARAMETER. Protected purposes go to the private
 * content disk; only thumbnails go to the public one. Letting a caller choose
 * would eventually mean a caller choosing wrong, and the failure mode is a
 * paid video on a public URL.
 */
final class MediaStorageService
{
    public function __construct(
        private readonly MediaPathResolver $paths,
        private readonly FileValidationService $validator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Validate and store an uploaded file against a model.
     *
     * @throws \App\Exceptions\MediaValidationException when validation fails.
     */
    public function store(
        UploadedFile $file,
        Model $attachable,
        MediaPurpose $purpose,
        ?User $uploader = null,
        bool $downloadable = false,
    ): MediaFile {
        // Nothing is written until this returns cleanly.
        $this->validator->validate($file, $purpose);

        $ulid = (string) Str::ulid();
        $extension = $this->validator->normalisedExtension($file);
        $disk = $this->diskFor($purpose);
        $path = $this->paths->resolve($attachable, $purpose, $ulid, $extension);

        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw new \App\Exceptions\MediaValidationException('The uploaded file could not be read.');
        }

        // Streamed, not read into memory: a 2 GB video must not require 2 GB
        // of PHP memory (FR-FILE-13).
        $stored = Storage::disk($disk)->put($path, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        /*
         * ═════════════════════════════════════════════════════════════════
         * THE RETURN VALUE IS CHECKED, BECAUSE THE DISK DOES NOT THROW.
         *
         * Both the `content` and `s3` disks are configured `throw => false`,
         * so a failed write returns FALSE and execution simply carries on.
         * Unchecked, the next lines happily created the media row, the
         * uploader reported success, and no file existed anywhere.
         *
         * On the local disk that needed something exotic — a full volume, a
         * permissions change. Once the content disk moved to Backblaze it
         * became ordinary: a dropped connection, an expired application key,
         * a bucket quota, a revoked permission. Every one of those returns
         * false rather than raising.
         *
         * A missing video that the system believes it has is worse than a
         * failed upload, because nobody goes looking for it until a student
         * opens the lesson.
         * ═════════════════════════════════════════════════════════════════
         */
        if ($stored === false) {
            throw new \App\Exceptions\MediaValidationException(
                'The file could not be saved to storage. Nothing was recorded — please try again, '
                .'and if it keeps happening the storage service may be unreachable.',
            );
        }

        try {
            $media = DB::transaction(function () use (
                $attachable, $purpose, $ulid, $extension, $disk, $path, $file, $uploader, $downloadable
            ): MediaFile {
                $media = new MediaFile;

                $media->fill([
                    'original_name' => $this->safeOriginalName($file),
                    'mime_type' => (string) $file->getClientMimeType(),
                    'extension' => $extension,
                    'size_bytes' => (int) $file->getSize(),
                    'checksum_sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
                    'purpose' => $purpose,
                    // A video is streamed, never offered as a download,
                    // regardless of what the caller asked for (FR-FILE-09).
                    'is_downloadable' => $purpose->isStreamed() ? false : $downloadable,
                    'position' => $this->nextPosition($attachable),
                ]);

                // Not fillable — assigned explicitly (NFR-SEC-07). A fillable
                // disk or path would let a request point a media record at an
                // arbitrary file, including another course's.
                $media->ulid = $ulid;
                $media->disk = $disk;
                $media->path = $path;
                $media->uploaded_by = $uploader?->getKey();

                $media->attachable()->associate($attachable);
                $media->save();

                return $media;
            });
        } catch (Throwable $e) {
            // The file landed but the row did not. Remove the file so our own
            // failure path cannot create an orphan.
            Storage::disk($disk)->delete($path);

            throw $e;
        }

        $this->audit->record(
            action: 'media.uploaded',
            actor: $uploader,
            subject: $media,
            description: sprintf(
                'Uploaded %s (%s, %s) to %s #%s.',
                $media->original_name,
                $purpose->value,
                $media->humanSize(),
                class_basename($attachable),
                (string) $attachable->getKey(),
            ),
        );

        return $media;
    }

    /**
     * Delete a file and its record.
     *
     * The file goes first: if the row were deleted first and the file removal
     * then failed, the file would be orphaned with nothing left pointing at
     * it to find it by.
     */
    public function delete(MediaFile $media, ?User $actor = null): void
    {
        $description = sprintf('Deleted %s (%s).', $media->original_name, $media->purpose->value);

        Storage::disk($media->disk)->delete($media->path);

        $this->audit->record(
            action: 'media.deleted',
            actor: $actor,
            subject: $media,
            description: $description,
        );

        $media->delete();
    }

    /**
     * Remove every stored file belonging to a model.
     *
     * Deletes the whole directory rather than enumerating rows, so a file
     * whose database row failed to save is still cleaned up.
     */
    public function deleteDirectoryFor(Model $attachable): void
    {
        $directory = $this->paths->directoryFor($attachable);

        foreach ([config()->string('lms.disks.content'), 'public'] as $disk) {
            if (Storage::disk($disk)->exists($directory)) {
                Storage::disk($disk)->deleteDirectory($directory);
            }
        }
    }

    /**
     * Protected content goes to the private disk; only thumbnails are public.
     *
     * Deliberately not a caller-supplied parameter — see class docblock.
     */
    private function diskFor(MediaPurpose $purpose): string
    {
        return $purpose->isProtected()
            ? config()->string('lms.disks.content')
            : 'public';
    }

    /**
     * Keep the user's filename as metadata, sanitised.
     *
     * It is never used as a path — the stored filename comes from the ULID —
     * but it is displayed in the admin UI, so it must not carry markup or
     * path separators.
     */
    private function safeOriginalName(UploadedFile $file): string
    {
        $name = (string) $file->getClientOriginalName();
        $name = str_replace(['/', '\\', "\0"], '', $name);

        return mb_substr(strip_tags($name), 0, 255);
    }

    private function nextPosition(Model $attachable): int
    {
        return (int) MediaFile::query()
            ->where('attachable_type', $attachable::class)
            ->where('attachable_id', $attachable->getKey())
            ->max('position') + 1;
    }
}
