<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\MediaPurpose;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * THE ONLY CLASS IN THIS CODEBASE THAT MAY CONSTRUCT A STORAGE PATH.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * planning.md rule S-2 · FR-FILE-11 · architecture.md §15.2
 * ─────────────────────────────────────────────────────────────────────────
 *
 * WHY THIS EXISTS AT ALL:
 *
 * V1 stores content at        courses/{id}/lessons/{id}/{purpose}/{ulid}.{ext}
 * V2 will store it at   org/{orgId}/courses/{id}/lessons/{id}/{purpose}/{ulid}.{ext}
 *
 * If paths were built inline wherever a file is saved, adding that `org/`
 * prefix in V2 would mean finding and correcting every string concatenation
 * in the application — and missing one would silently write a new
 * organisation's content into another's directory. Funnelling every path
 * through this one class turns that migration into a change to a single
 * method, with every existing object still resolvable because the old prefix
 * keeps working.
 *
 * That is the difference between "add a column" and "rewrite the
 * application", which is precisely the outcome the customer asked us to
 * guarantee.
 *
 * SECURITY: filenames are ALWAYS system-generated from the media ULID
 * (FR-FILE-05). The user's original filename is never used on disk, so an
 * upload called "invoice.pdf.php" or "../../.env" cannot become a path.
 */
final class MediaPathResolver
{
    /**
     * Build the storage path for a file attached to a model.
     *
     * @param  Model  $attachable  Currently a Course or a Lesson.
     * @param  string  $ulid  The MediaFile's ULID — becomes the filename.
     * @param  string  $extension  Validated extension, without a dot.
     */
    public function resolve(Model $attachable, MediaPurpose $purpose, string $ulid, string $extension): string
    {
        return $this->prefix()
            .$this->attachableSegment($attachable)
            .'/'.$purpose->value
            .'/'.$this->filename($ulid, $extension);
    }

    /**
     * The directory holding every file for one attachable.
     *
     * Used by cleanup when a lesson or course is deleted — delete the
     * directory rather than enumerating files, so nothing is left behind by a
     * media row that failed to save.
     */
    public function directoryFor(Model $attachable): string
    {
        return $this->prefix().$this->attachableSegment($attachable);
    }

    /**
     * Scratch location for an in-flight upload, before validation passes.
     *
     * Deliberately OUTSIDE the content tree: a file that has not yet been
     * validated must never sit, even briefly, at a path the delivery layer
     * could serve.
     */
    public function temporaryPath(string $ulid, string $extension): string
    {
        return 'uploads/'.$this->filename($ulid, $extension);
    }

    /**
     * THE MULTI-TENANCY SEAM.
     *
     * V1 returns an empty prefix. V2 returns "org/{organisation_id}/" from
     * the tenant context, and every path in the system becomes
     * organisation-scoped without another line changing anywhere else.
     *
     * Objects written before the migration keep resolving because their
     * `media_files.path` column already holds their full stored path — this
     * resolver is only consulted when writing something new.
     */
    private function prefix(): string
    {
        return '';
    }

    private function attachableSegment(Model $attachable): string
    {
        return match (true) {
            $attachable instanceof Course => 'courses/'.$attachable->getKey(),
            $attachable instanceof Lesson => 'courses/'.$this->courseIdFor($attachable).'/lessons/'.$attachable->getKey(),
            default => throw new InvalidArgumentException(
                'Cannot resolve a storage path for ['.$attachable::class.']. '
                .'Add it to MediaPathResolver rather than building a path at the call site.',
            ),
        };
    }

    /**
     * A lesson's files live under its course, so deleting a course removes
     * its whole tree in one operation.
     */
    private function courseIdFor(Lesson $lesson): int|string
    {
        $course = $lesson->course();

        if ($course === null) {
            throw new InvalidArgumentException(
                "Lesson [{$lesson->getKey()}] has no resolvable course; refusing to guess a storage path.",
            );
        }

        return $course->getKey();
    }

    /**
     * System-generated filename. Never the user's.
     *
     * The extension is stripped of anything that is not alphanumeric, so a
     * validated extension cannot still smuggle a path separator or a second
     * dot into the filename.
     */
    private function filename(string $ulid, string $extension): string
    {
        $safeExtension = mb_strtolower(preg_replace('/[^A-Za-z0-9]/', '', $extension) ?? '');

        if ($safeExtension === '') {
            throw new InvalidArgumentException('Refusing to build a storage path with an empty extension.');
        }

        return $ulid.'.'.$safeExtension;
    }
}
