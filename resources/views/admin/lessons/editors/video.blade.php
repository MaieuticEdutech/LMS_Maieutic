{{--
    Video lesson editor (App\Services\Content\Handlers\VideoContentHandler).
    Included inside LessonEditor's form — wire:model targets bind to that
    parent component's public properties.
--}}
<x-input
    wire:model="duration_seconds"
    type="number"
    min="1"
    label="Duration (seconds)"
    name="duration_seconds"
    hint="Drives the course total and the player's time display."
    required
/>

<div>
    <span class="mb-1.5 block text-sm font-medium text-neutral-900">Video file</span>
    <livewire:admin.courses.media-uploader
        :attachable="$lesson"
        purpose="video"
        :multiple="false"
        :downloadable="false"
        wire:key="primary-media-{{ $lesson->id }}"
    />
</div>
