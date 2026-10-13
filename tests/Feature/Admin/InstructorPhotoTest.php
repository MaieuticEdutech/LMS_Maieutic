<?php

declare(strict_types=1);

use App\Actions\Admin\SetInstructorPhoto;
use App\Enums\MediaPurpose;
use App\Livewire\Admin\InstructorForm;
use App\Models\InstructorProfile;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Instructor profile photos
|--------------------------------------------------------------------------
|
| Public photos shown on course cards and pages. They go through the same
| media pipeline as course thumbnails, so they land on the PUBLIC disk, never
| the private content disk, and never anywhere near the encrypted student
| profile documents.
*/

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('content');

    $this->admin = User::factory()->superAdmin()->create();
    $this->instructor = User::factory()->instructor()->create();
});

/**
 * @return Collection<int, MediaFile>
 */
function profilePhotos(User $instructor): Collection
{
    $profile = InstructorProfile::query()->where('user_id', $instructor->getKey())->first();

    return $profile === null
        ? new Collection
        : MediaFile::query()->where('attachable_type', $profile->getMorphClass())->where('attachable_id', $profile->getKey())->get();
}

it('stores an uploaded photo on the public disk against the instructor profile', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(InstructorForm::class, ['instructor' => $this->instructor])
        ->set('email', $this->instructor->email)
        ->set('photo', UploadedFile::fake()->image('face.jpg', 400, 400))
        ->call('save')
        ->assertHasNoErrors();

    $photos = profilePhotos($this->instructor);
    expect($photos)->toHaveCount(1);

    $photo = $photos->firstOrFail();
    expect($photo->purpose)->toBe(MediaPurpose::Thumbnail)
        ->and($photo->disk)->toBe('public');

    Storage::disk('public')->assertExists($photo->path);
});

it('replaces the old photo instead of keeping both', function (): void {
    $set = app(SetInstructorPhoto::class);

    $first = $set->handle($this->instructor, UploadedFile::fake()->image('one.jpg'), $this->admin);
    $set->handle($this->instructor, UploadedFile::fake()->image('two.jpg'), $this->admin);

    expect(profilePhotos($this->instructor))->toHaveCount(1);
    Storage::disk('public')->assertMissing($first->path);
});

it('removes the photo', function (): void {
    $set = app(SetInstructorPhoto::class);
    $media = $set->handle($this->instructor, UploadedFile::fake()->image('one.jpg'), $this->admin);

    $set->remove($this->instructor, $this->admin);

    expect(profilePhotos($this->instructor))->toBeEmpty();
    Storage::disk('public')->assertMissing($media->path);
});

it('rejects a file that is not an image', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(InstructorForm::class, ['instructor' => $this->instructor])
        ->set('email', $this->instructor->email)
        ->set('photo', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['photo']);

    expect(profilePhotos($this->instructor))->toBeEmpty();
});

it('exposes a public URL once the photo relation is loaded, and null otherwise', function (): void {
    app(SetInstructorPhoto::class)->handle($this->instructor, UploadedFile::fake()->image('one.jpg'), $this->admin);

    $profile = InstructorProfile::query()->where('user_id', $this->instructor->getKey())->firstOrFail();

    expect($profile->photoUrl())->toBeNull()
        ->and($profile->load('photo')->photoUrl())->toBeString()->toContain('instructors/');
});

it('does not let an instructor reach the admin form to change photos', function (): void {
    $this->actingAs($this->instructor)
        ->get(route('admin.instructors.edit', $this->instructor))
        ->assertForbidden();
});
