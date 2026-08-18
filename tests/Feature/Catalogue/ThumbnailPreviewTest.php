<?php

declare(strict_types=1);

use App\Enums\MediaPurpose;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\Module;

/*
|--------------------------------------------------------------------------
| The admin has to be able to SEE the thumbnail they uploaded
|--------------------------------------------------------------------------
|
| Reported from use, after the catalogue side was already fixed: "I apply a
| thumbnail to a course and it is not visible for students or admin."
|
| The students' half was environmental — public/storage was never linked, so
| every thumbnail URL 404'd and the card fell back to its gradient. The admin's
| half was real: the uploader listed a paper icon, a filename and a size, and
| never attempted to load the image. That row looks identical whether the
| pipeline works perfectly or is completely broken, which is exactly why a
| missing symlink could sit there unnoticed.
|
| previewUrl() draws the line. It is a security boundary, not a convenience:
| thumbnails are the ONLY public medium in this system, and every other upload
| is reached through an authorised controller. A plain URL for a lesson video
| would be an unauthenticated link to protected content.
|
*/

it('offers a preview URL for a thumbnail', function (): void {
    $course = Course::factory()->create();

    $thumbnail = MediaFile::factory()->create([
        'attachable_type' => Course::class,
        'attachable_id' => $course->getKey(),
        'purpose' => MediaPurpose::Thumbnail,
        'disk' => 'public',
        'path' => 'courses/1/thumbnail/cover.png',
        'mime_type' => 'image/png',
    ]);

    expect($thumbnail->previewUrl())
        ->toBeString()
        ->toContain('courses/1/thumbnail/cover.png');
});

it('refuses a preview URL for protected media, whatever its mime type claims', function (): void {
    /*
     * The important case. A record on the private content disk has no plain
     * URL by design; handing one out would route around MediaPolicy entirely.
     * The mime type is deliberately set to an image here — claiming to be a
     * picture must not be enough to earn a public link.
     */
    $course = Course::factory()->create();
    $module = Module::factory()->for($course)->create();
    $lesson = Lesson::factory()->for($module)->create();

    foreach ([MediaPurpose::Video, MediaPurpose::Document, MediaPurpose::Presentation, MediaPurpose::Attachment] as $purpose) {
        $file = MediaFile::factory()->create([
            'attachable_type' => Lesson::class,
            'attachable_id' => $lesson->getKey(),
            'purpose' => $purpose,
            'mime_type' => 'image/png',
        ]);

        expect($file->previewUrl())->toBeNull();
    }
});

it('refuses a preview URL for a thumbnail that is not an image', function (): void {
    // Belt and braces: the upload pipeline sniffs content types, but a row
    // written by a seeder or a future import should not put a PDF in an <img>.
    $course = Course::factory()->create();

    $file = MediaFile::factory()->create([
        'attachable_type' => Course::class,
        'attachable_id' => $course->getKey(),
        'purpose' => MediaPurpose::Thumbnail,
        'disk' => 'public',
        'mime_type' => 'application/pdf',
    ]);

    expect($file->previewUrl())->toBeNull();
});
