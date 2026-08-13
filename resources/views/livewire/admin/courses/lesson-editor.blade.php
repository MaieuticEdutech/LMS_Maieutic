<div>
    <x-modal name="lesson-editor-{{ $lesson->id }}" placement="right" title="Lesson content">
        <form wire:submit="save" id="lesson-editor-form-{{ $lesson->id }}" class="space-y-5">
            <x-input wire:model="title" label="Lesson title" name="title" required />

            <div class="rounded-control bg-neutral-50 px-3 py-2 text-xs text-neutral-500">
                Content type: <span class="font-semibold text-neutral-700">{{ $lesson->type->label() }}</span> — not
                changeable after creation. Delete and recreate the lesson to switch types.
            </div>

            @include($editorView, ['lesson' => $lesson])

            <div>
                <span class="mb-1.5 block text-sm font-medium text-neutral-900">
                    {{ $lesson->type === \App\Enums\LessonType::Resource ? 'Files' : 'Supplementary attachments' }}
                </span>
                <livewire:admin.courses.media-uploader
                    :attachable="$lesson"
                    purpose="attachment"
                    :multiple="true"
                    :downloadable="true"
                    wire:key="attachments-{{ $lesson->id }}"
                />
            </div>

            <x-checkbox wire:model="is_published" label="Published — visible to enrolled students" name="is_published" />
        </form>

        <x-slot:footer>
            <x-button x-on:click="$dispatch('close-modal', 'lesson-editor-{{ $lesson->id }}')" variant="ghost" size="sm">Cancel</x-button>
            <x-button type="submit" form="lesson-editor-form-{{ $lesson->id }}" size="sm">Save lesson</x-button>
        </x-slot:footer>
    </x-modal>
</div>
