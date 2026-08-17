<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Exceptions\MediaValidationException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\Module;
use App\Models\User;
use App\Services\Media\MediaStorageService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/*
|--------------------------------------------------------------------------
| A storage write that fails must not look like one that worked
|--------------------------------------------------------------------------
|
| ═════════════════════════════════════════════════════════════════════════
| BOTH CONTENT DISKS ARE CONFIGURED `throw => false`.
|
| So `Storage::disk(...)->put()` returns FALSE on failure rather than raising.
| That return value was not checked: the media row was created regardless, the
| uploader reported success, and no file existed anywhere.
|
| On the local disk this needed something exotic — a full volume, a
| permissions change. Once the content disk moved to Backblaze it became
| ordinary: a dropped connection, an expired application key, a bucket quota.
| Every one of those returns false rather than throwing.
|
| A missing video the system believes it has is worse than a failed upload,
| because nobody finds out until a student opens the lesson.
| ═════════════════════════════════════════════════════════════════════════
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();

    $course = Course::factory()->create();
    $module = Module::factory()->forCourse($course)->create();

    $this->lesson = Lesson::factory()->forModule($module)->create(['type' => LessonType::Document]);

    /*
     * A file with REAL magic bytes. UploadedFile::fake() writes an empty file,
     * which the content sniff rejects as `application/x-empty` long before
     * storage is reached — so a fake would test the validator, not this.
     */
    $this->validPdf = function (): UploadedFile {
        $directory = storage_path('framework/testing/uploads');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.'storefail_'.bin2hex(random_bytes(8));
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

        return new UploadedFile($path, 'notes.pdf', 'application/pdf', null, true);
    };

    /*
     * A real FilesystemAdapter whose put() returns false.
     *
     * A subclass rather than a Mockery double, for two reasons. It is the
     * genuine Laravel class, so anything else the service calls on the disk
     * behaves normally instead of needing an expectation declared for it. And
     * Mockery's fluent chain returns a union type that Larastan cannot narrow,
     * which would fail the analyse gate.
     *
     * Making a LOCAL disk fail for real does not reproduce this: the local
     * driver throws UnableToCreateDirectory, which `throw => false` does not
     * catch. A false return is what the S3 driver gives on a network or
     * permission failure, and that is the case being guarded.
     */
    $this->failingDisk = function (): void {
        $root = storage_path('framework/testing/failing-disk');

        if (! is_dir($root)) {
            mkdir($root, 0777, true);
        }

        $adapter = new LocalFilesystemAdapter($root);

        $disk = new class(new Flysystem($adapter), $adapter, ['throw' => false]) extends FilesystemAdapter
        {
            public function put($path, $contents, $options = [])
            {
                return false;
            }
        };

        Storage::set('storage_failure', $disk);
        config()->set('lms.disks.content', 'storage_failure');
    };
});

it('refuses to record a media row when the write to storage fails', function (): void {
    ($this->failingDisk)();

    expect(fn () => app(MediaStorageService::class)->store(
        ($this->validPdf)(),
        $this->lesson,
        MediaPurpose::Document,
        $this->admin,
    ))->toThrow(MediaValidationException::class);

    // The row is the part that matters: a record pointing at a file that was
    // never written is what turns a transient failure into a permanent
    // mystery, discovered by a student rather than by anyone who could fix it.
    expect(MediaFile::query()->count())->toBe(0);
});

it('explains the failure in words an administrator can act on', function (): void {
    ($this->failingDisk)();

    try {
        app(MediaStorageService::class)->store(
            ($this->validPdf)(),
            $this->lesson,
            MediaPurpose::Document,
            $this->admin,
        );

        $this->fail('The store should have thrown.');
    } catch (MediaValidationException $e) {
        // "Nothing was recorded" stops somebody hunting for a half-created
        // row; the storage hint points at the bucket rather than the file,
        // which is where the fault actually is.
        expect($e->getMessage())
            ->toContain('could not be saved to storage')
            ->toContain('Nothing was recorded');
    }
});
