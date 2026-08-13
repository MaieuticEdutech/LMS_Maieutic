{{--
    Presentation lesson editor (App\Services\Content\Handlers\PresentationContentHandler).
    Included inside LessonEditor's form. Downloadable in MVP — no in-browser
    preview is required (FR-FILE-14).
--}}
<div>
    <span class="mb-1.5 block text-sm font-medium text-neutral-900">Presentation file (PPT/PPTX/ODP)</span>
    <livewire:admin.courses.media-uploader
        :attachable="$lesson"
        purpose="presentation"
        :multiple="false"
        :downloadable="true"
        wire:key="primary-media-{{ $lesson->id }}"
    />
</div>
