<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Livewire\Admin\Courses\MediaUploader;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| MediaUploader — Livewire wiring only
|--------------------------------------------------------------------------
|
| The validation RULES themselves (size ceilings, extension allow-lists,
| content sniffing, AC-21) are already exhaustively covered at the action
| level in tests/Feature/Catalogue/MediaUploadTest.php. This file only
| proves the component calls the right action for the right situation and
| surfaces a rejection instead of throwing. set('file', ...) triggers
| Livewire's updatedFile() hook automatically — no explicit call() needed.
*/

function realPdfUpload(string $filename = 'notes.pdf'): UploadedFile
{
    // ::fake()->createWithContent() — not a raw `new UploadedFile(...)` —
    // because Livewire's test harness only recognises files produced by the
    // fake factory when wiring up a WithFileUploads property via set().
    // Real magic bytes are still needed: FileValidationService sniffs
    // content, and a fake() file full of random bytes would never exercise
    // that check.
    return UploadedFile::fake()->createWithContent(
        $filename,
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n",
    );
}

beforeEach(function (): void {
    Storage::fake('content');
    Storage::fake('public');

    $this->admin = User::factory()->superAdmin()->create();
    $this->actingAs($this->admin);
});

it('attaches a valid document to a document lesson', function (): void {
    $lesson = Lesson::factory()->ofType(LessonType::Document)->create();

    Livewire::test(MediaUploader::class, [
        'attachable' => $lesson,
        'purpose' => 'document',
        'multiple' => false,
        'downloadable' => true,
    ])->set('file', realPdfUpload());

    expect(MediaFile::query()->where('purpose', 'document')->count())->toBe(1);
});

it('rejects a disallowed extension before storing anything', function (): void {
    $lesson = Lesson::factory()->ofType(LessonType::Document)->create();

    Livewire::test(MediaUploader::class, [
        'attachable' => $lesson,
        'purpose' => 'document',
        'multiple' => false,
        'downloadable' => true,
    ])
        ->set('file', UploadedFile::fake()->create('malware.exe', 10))
        ->assertHasErrors(['file']);

    expect(MediaFile::query()->count())->toBe(0);
});

it('replaces an existing single-file slot rather than adding a second row', function (): void {
    $lesson = Lesson::factory()->ofType(LessonType::Document)->create();

    Livewire::test(MediaUploader::class, [
        'attachable' => $lesson,
        'purpose' => 'document',
        'multiple' => false,
        'downloadable' => true,
    ])
        ->set('file', realPdfUpload('first.pdf'))
        ->set('file', realPdfUpload('second.pdf'));

    expect(MediaFile::query()->where('purpose', 'document')->count())->toBe(1);

    $file = MediaFile::query()->where('purpose', 'document')->firstOrFail();

    expect($file->original_name)->toBe('second.pdf');
});

it('attaches a thumbnail to a course', function (): void {
    $course = Course::factory()->create();

    Livewire::test(MediaUploader::class, [
        'attachable' => $course,
        'purpose' => 'thumbnail',
        'multiple' => false,
        'downloadable' => false,
    ])->set('file', UploadedFile::fake()->image('cover.jpg', 40, 40));

    expect($course->media()->where('purpose', 'thumbnail')->exists())->toBeTrue();
});

it('removes a file on confirmation', function (): void {
    $lesson = Lesson::factory()->ofType(LessonType::Document)->create();

    $component = Livewire::test(MediaUploader::class, [
        'attachable' => $lesson,
        'purpose' => 'document',
        'multiple' => false,
        'downloadable' => true,
    ])->set('file', realPdfUpload());

    $media = MediaFile::query()->where('purpose', 'document')->firstOrFail();

    $component->call('confirmRemove', $media->id)->call('remove');

    expect(MediaFile::query()->whereKey($media->id)->exists())->toBeFalse();
});

it('denies uploading to an instructor', function (): void {
    $this->actingAs(User::factory()->instructor()->create());

    $lesson = Lesson::factory()->ofType(LessonType::Document)->create();

    Livewire::test(MediaUploader::class, [
        'attachable' => $lesson,
        'purpose' => 'document',
        'multiple' => false,
        'downloadable' => true,
    ])
        ->set('file', realPdfUpload())
        ->assertForbidden();
});
