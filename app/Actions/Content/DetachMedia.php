<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\MediaFile;
use App\Models\User;
use App\Services\Media\MediaStorageService;

/**
 * Remove a file from a lesson or course (FR-FILE-12).
 *
 * Deletes immediately rather than queuing, because this is a deliberate
 * single-file action by an administrator who expects to see it gone. The
 * queued DeleteOrphanedMedia job exists for the bulk case — deleting a lesson
 * or a whole course — where the work is unbounded and must not block a
 * request.
 *
 * Note the consequence: removing the file a lesson depends on will block that
 * course from publishing, because the content handler's `publishBlockers()`
 * reports the missing media. That is intended — it surfaces as a clear
 * checklist item rather than as a published lesson with an empty player.
 */
final class DetachMedia
{
    public function __construct(private readonly MediaStorageService $storage) {}

    public function handle(MediaFile $media, User $actor): void
    {
        $this->storage->delete($media, $actor);
    }
}
