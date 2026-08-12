<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\MediaFile;
use App\Models\User;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Swap a lesson's file for a new one (FR-FILE-01).
 *
 * ORDER MATTERS: the new file is stored FIRST, and the old one is removed
 * only once the replacement has succeeded.
 *
 * The obvious order — delete then upload — loses the original if the new
 * upload fails validation, leaving a lesson with no content at all. Since
 * validation happens inside `store()`, a rejected replacement must leave the
 * existing file untouched.
 */
final class ReplaceMedia
{
    public function __construct(private readonly MediaStorageService $storage) {}

    public function handle(MediaFile $existing, UploadedFile $file, User $actor): MediaFile
    {
        $attachable = $existing->attachable;

        if ($attachable === null) {
            throw new RuntimeException(
                'Cannot replace a media file whose owner no longer exists.',
            );
        }

        // Store the replacement first. If validation rejects it, this throws
        // and the original is still in place.
        $replacement = $this->storage->store(
            file: $file,
            attachable: $attachable,
            purpose: $existing->purpose,
            uploader: $actor,
            downloadable: $existing->is_downloadable,
        );

        // Keep the original's ordering so the replacement appears where the
        // old file did.
        $replacement->forceFill(['position' => $existing->position])->save();

        // Only now is it safe to remove the old one.
        $this->storage->delete($existing, $actor);

        return $replacement;
    }
}
