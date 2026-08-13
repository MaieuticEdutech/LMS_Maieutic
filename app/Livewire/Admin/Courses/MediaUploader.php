<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Courses;

use App\Actions\Content\AttachMedia;
use App\Actions\Content\DetachMedia;
use App\Actions\Content\ReplaceMedia;
use App\Enums\MediaPurpose;
use App\Exceptions\MediaValidationException;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Generic upload widget reused for a course thumbnail, a lesson's primary
 * asset and a lesson's supplementary attachments (docs/UI-GUIDE.md, Course
 * Builder). Parameterised by attachable + purpose rather than duplicated
 * per use — the same "add a prop, don't fork" rule the guide states for
 * Blade components applies equally to a Livewire one owned by this track.
 *
 * Deliberately thin: MediaStorageService (via the Attach/Replace/Detach
 * actions) owns every validation and storage decision. This class only
 * decides WHICH action to call.
 */
final class MediaUploader extends Component
{
    use WithFileUploads;

    public Course|Lesson $attachable;

    public string $purpose;

    public bool $multiple = false;

    public bool $downloadable = true;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $file = null;

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $files = [];

    public ?int $removing = null;

    public function mount(Course|Lesson $attachable, string $purpose, bool $multiple = false, bool $downloadable = true): void
    {
        $this->attachable = $attachable;
        $this->purpose = $purpose;
        $this->multiple = $multiple;
        $this->downloadable = $downloadable;
    }

    public function updatedFile(): void
    {
        $this->store($this->file);
        $this->file = null;
    }

    public function updatedFiles(): void
    {
        foreach ($this->files as $file) {
            $this->store($file);
        }

        $this->files = [];
    }

    private function store(mixed $file): void
    {
        if ($file === null) {
            return;
        }

        $this->authorize('create', MediaFile::class);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $existing = $this->multiple ? null : $this->media()->first();

        try {
            if ($existing !== null) {
                $this->authorize('update', $existing);
                app(ReplaceMedia::class)->handle($existing, $file, $actor);
            } elseif ($this->attachable instanceof Course) {
                app(AttachMedia::class)->thumbnailTo($this->attachable, $file, $actor);
            } elseif ($this->purposeEnum() === MediaPurpose::Attachment) {
                app(AttachMedia::class)->attachmentToLesson($this->attachable, $file, $actor);
            } else {
                app(AttachMedia::class)->toLesson($this->attachable, $file, $actor);
            }
        } catch (MediaValidationException $e) {
            $this->addError('file', $e->getMessage());
        }
    }

    public function confirmRemove(int $mediaId): void
    {
        $this->removing = $mediaId;
    }

    public function remove(): void
    {
        if ($this->removing === null) {
            return;
        }

        $media = MediaFile::query()->findOrFail($this->removing);
        $this->authorize('delete', $media);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        app(DetachMedia::class)->handle($media, $actor);
        $this->removing = null;
    }

    private function purposeEnum(): MediaPurpose
    {
        return MediaPurpose::from($this->purpose);
    }

    /**
     * @return Collection<int, MediaFile>
     */
    public function media(): Collection
    {
        return $this->attachable->media()->ofPurpose($this->purposeEnum())->get();
    }

    public function render(): View
    {
        return view('livewire.admin.courses.media-uploader', [
            'media' => $this->media(),
        ]);
    }
}
