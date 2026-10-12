<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\MediaPurpose;
use App\Models\InstructorProfile;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\UploadedFile;

/**
 * Set or remove an instructor's public profile photo.
 *
 * Goes through MediaStorageService exactly like a course thumbnail (purpose
 * `thumbnail`): the same size ceiling, extension allow-list, sniffed mime type
 * and public disk. Storing and deleting are audited by that service.
 *
 * One photo per instructor: the old file is deleted only after the new one is
 * safely stored, so a failed upload never leaves the instructor with none.
 */
final class SetInstructorPhoto
{
    public function __construct(private readonly MediaStorageService $storage) {}

    public function handle(User $instructor, UploadedFile $file, User $actor): MediaFile
    {
        $profile = $this->profileFor($instructor);
        $profile->loadMissing('photo');
        $previous = $profile->photo;

        $media = $this->storage->store(
            file: $file,
            attachable: $profile,
            purpose: MediaPurpose::Thumbnail,
            uploader: $actor,
        );

        foreach ($previous as $old) {
            $this->storage->delete($old, $actor);
        }

        return $media;
    }

    public function remove(User $instructor, User $actor): void
    {
        $profile = $this->profileFor($instructor);
        $profile->loadMissing('photo');

        foreach ($profile->photo as $media) {
            $this->storage->delete($media, $actor);
        }
    }

    private function profileFor(User $instructor): InstructorProfile
    {
        // Query, don't trust a cached relation: the profile may have been
        // created moments ago (UpdateInstructor) after an earlier load cached
        // "no profile" on this model.
        $profile = $instructor->instructorProfile()->firstOrCreate([]);
        $instructor->setRelation('instructorProfile', $profile);

        return $profile;
    }
}
