<?php

declare(strict_types=1);

use App\Enums\MediaPurpose;
use App\Models\Course;
use App\Models\MediaFile;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Course thumbnails must actually appear
|--------------------------------------------------------------------------
|
| Reported from use: "when we add a thumbnail it doesn't render".
|
| The upload was never the problem — AttachMedia::thumbnailTo stored it
| correctly, on the `public` disk, which is right: a thumbnail is the one
| PUBLIC medium in the system (FR-STU-04) so a guest browsing the catalogue
| can load it with a plain URL.
|
| Nothing rendered it. partials/course-thumb.blade.php drew its two-tone
| gradient unconditionally and had no <img> at all, so every uploaded
| thumbnail was stored, counted by the publish validator, and invisible.
|
*/

beforeEach(function (): void {
    $this->course = Course::factory()->published()->create(['title' => 'Thumbnailed Course']);

    $this->attachThumbnail = function (Course $course): MediaFile {
        return MediaFile::factory()->create([
            'attachable_type' => Course::class,
            'attachable_id' => $course->getKey(),
            'purpose' => MediaPurpose::Thumbnail,
            'disk' => 'public',
            'path' => 'thumbnails/example-thumbnail.jpg',
        ]);
    };
});

it('renders an uploaded thumbnail on the public catalogue', function (): void {
    ($this->attachThumbnail)($this->course);

    $this->get(route('catalogue.index'))
        ->assertOk()
        ->assertSee('example-thumbnail.jpg', escape: false);
});

it('renders an uploaded thumbnail on the course detail page', function (): void {
    ($this->attachThumbnail)($this->course);

    $this->get(route('catalogue.show', $this->course))
        ->assertOk()
        ->assertSee('example-thumbnail.jpg', escape: false);
});

it('falls back to the gradient when a course has no thumbnail', function (): void {
    // No <img>, but the card must still render rather than collapse.
    $this->get(route('catalogue.index'))
        ->assertOk()
        ->assertSee('Thumbnailed Course')
        ->assertDontSee('example-thumbnail.jpg', escape: false);
});

it('shows the thumbnail to a guest, not only to signed-in users', function (): void {
    // The whole reason thumbnails live on the public disk: the catalogue is
    // reachable without an account (AC-01).
    ($this->attachThumbnail)($this->course);

    expect(auth()->check())->toBeFalse();

    $this->get(route('catalogue.index'))
        ->assertOk()
        ->assertSee('example-thumbnail.jpg', escape: false);
});

it('renders the student dashboard thumbnail without a lazy-load violation', function (): void {
    // preventLazyLoading() is active in testing, so an un-eager-loaded
    // thumbnail relation would throw here rather than merely be slow.
    $student = User::factory()->create();

    $this->actingAs($student)
        ->get(route('student.home'))
        ->assertOk();
});
