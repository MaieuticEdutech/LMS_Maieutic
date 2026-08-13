{{--
    Document lesson editor (App\Services\Content\Handlers\DocumentContentHandler).
    Included inside LessonEditor's form.
--}}
<div>
    <span class="mb-1.5 block text-sm font-medium text-neutral-900">Document file (PDF)</span>
    <livewire:admin.courses.media-uploader
        :attachable="$lesson"
        purpose="document"
        :multiple="false"
        :downloadable="true"
        wire:key="primary-media-{{ $lesson->id }}"
    />
</div>
