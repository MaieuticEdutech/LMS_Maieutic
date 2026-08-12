<?php

declare(strict_types=1);

namespace App\Jobs\Media;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Services\Media\MediaStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Removes stored files belonging to a deleted lesson or course
 * (FR-FILE-12, phases.md Phase 5).
 *
 * ─────────────────────────────────────────────────────────────────────────
 * OWNERSHIP NOTE — flagged, not assumed.
 *
 * `app/Jobs/**` is listed as Track C's in planning.md §21.2.2, but this job
 * is explicitly part of Phase 5 (Track A). It lives in `app/Jobs/Media/` — a
 * new directory nothing else touches — so it cannot collide with Track C's
 * queue work in Phase 11.
 *
 * The ownership rule should probably be refined to "Shashank owns queue
 * configuration and app/Jobs/{Mail,Billing,Reporting}; each track owns its own
 * domain jobs". Raised for the team rather than silently taken.
 * ─────────────────────────────────────────────────────────────────────────
 *
 * WHY ASYNCHRONOUS: deleting from remote object storage is slow and can fail
 * transiently. Doing it inline would make an admin's delete request hang on a
 * network round-trip, and a storage failure would roll back a database delete
 * that had already succeeded.
 *
 * IDEMPOTENT: deleting an already-deleted file is a no-op, so a retry is
 * always safe. That matters because this runs on a queue with retries.
 *
 * ORDER: the model row is removed first (by the calling Action), then files.
 * The reverse would risk deleting files while a record still pointed at them.
 */
class DeleteOrphanedMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * @param  class-string<Course|Lesson>  $attachableType
     */
    /**
     * Public readonly, not private: the payload IS the contract (seam S-4),
     * and dispatching cleanup for the wrong id would delete a different
     * lesson's files. Tests assert on these; readonly still forbids mutation.
     */
    public function __construct(
        public readonly string $attachableType,
        public readonly int $attachableId,
    ) {}

    public function handle(MediaStorageService $storage): void
    {
        $records = MediaFile::query()
            ->where('attachable_type', $this->attachableType)
            ->where('attachable_id', $this->attachableId)
            ->get();

        foreach ($records as $media) {
            try {
                $storage->delete($media);
            } catch (Throwable $e) {
                // One unreachable file must not abandon the rest. Logged so
                // the leak is visible rather than silent, then continue.
                Log::warning('Failed to delete media file during cleanup.', [
                    'media_id' => $media->getKey(),
                    'disk' => $media->disk,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        /*
         * Then remove the whole directory. This catches files that were
         * written but whose database row never saved — the one orphan class
         * the loop above cannot see, because there is no record to find them
         * by.
         */
        $attachable = $this->attachableType::withTrashed()->find($this->attachableId);

        if ($attachable !== null) {
            $storage->deleteDirectoryFor($attachable);
        }
    }
}
