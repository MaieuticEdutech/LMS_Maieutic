<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Enums\MediaPurpose;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Content\ContentTypeRegistry;
use App\Services\Media\MediaStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Attach an uploaded file to a lesson or course (FR-FILE-01, FR-FILE-02).
 *
 * A thin orchestration layer: MediaStorageService owns validation, storage
 * and the database row. This action's job is to decide the PURPOSE, which is
 * the thing the caller must not be trusted to choose freely.
 *
 * For a lesson the purpose comes from its content type's handler, so a video
 * lesson can only ever receive a video and a document lesson a document. A
 * caller-supplied purpose would let a request store an executable under the
 * `attachment` allow-list against a video lesson and bypass the tighter rules.
 */
final class AttachMedia
{
    public function __construct(
        private readonly MediaStorageService $storage,
        private readonly ContentTypeRegistry $registry,
    ) {}

    /**
     * Attach the file a lesson's own type expects.
     */
    public function toLesson(Lesson $lesson, UploadedFile $file, User $actor): MediaFile
    {
        $purpose = $this->registry->for($lesson->type)->mediaPurpose();

        if ($purpose === null) {
            throw new InvalidArgumentException(sprintf(
                'A %s lesson does not accept an uploaded file.',
                $lesson->type->value,
            ));
        }

        return $this->storage->store(
            file: $file,
            attachable: $lesson,
            purpose: $purpose,
            uploader: $actor,
            // Everything except video is offered as a download; video is
            // streamed and MediaStorageService enforces that regardless.
            downloadable: ! $purpose->isStreamed(),
        );
    }

    /**
     * Attach a supplementary downloadable resource to a lesson of any type.
     *
     * Distinct from the lesson's primary content — a video lesson may still
     * carry a worksheet (FR-FILE-08).
     */
    public function attachmentToLesson(Lesson $lesson, UploadedFile $file, User $actor): MediaFile
    {
        return $this->storage->store(
            file: $file,
            attachable: $lesson,
            purpose: MediaPurpose::Attachment,
            uploader: $actor,
            downloadable: true,
        );
    }

    /**
     * Attach a course thumbnail — the only PUBLIC media in the system
     * (FR-STU-04).
     */
    public function thumbnailTo(Model $attachable, UploadedFile $file, User $actor): MediaFile
    {
        return $this->storage->store(
            file: $file,
            attachable: $attachable,
            purpose: MediaPurpose::Thumbnail,
            uploader: $actor,
            downloadable: false,
        );
    }
}
