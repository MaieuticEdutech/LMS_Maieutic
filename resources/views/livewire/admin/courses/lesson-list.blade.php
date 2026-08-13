<div x-data="{
    dragIndex: null,
    onDrop(ids, targetIndex) {
        if (this.dragIndex === null || this.dragIndex === targetIndex) return;
        const reordered = [...ids];
        const [moved] = reordered.splice(this.dragIndex, 1);
        reordered.splice(targetIndex, 0, moved);
        this.dragIndex = null;
        $wire.reorder(reordered);
    },
}">
    @php $lessonIds = $lessons->pluck('id')->all(); @endphp

    @foreach ($lessons as $li => $lesson)
        <div
            wire:key="lesson-{{ $lesson->id }}"
            x-on:dragover.prevent
            x-on:drop.prevent="onDrop(@js($lessonIds), {{ $li }})"
            class="flex items-center gap-2.5 border-b border-neutral-100 py-2.5 pl-10 pr-4 hover:bg-neutral-50"
        >
            <span
                draggable="true"
                x-on:dragstart="dragIndex = {{ $li }}"
                class="cursor-grab text-neutral-400"
                aria-hidden="true"
            >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="6" r="1"></circle><circle cx="15" cy="6" r="1"></circle><circle cx="9" cy="12" r="1"></circle><circle cx="15" cy="12" r="1"></circle><circle cx="9" cy="18" r="1"></circle><circle cx="15" cy="18" r="1"></circle></svg>
            </span>

            <span class="text-teal-600" aria-hidden="true">
                @if ($lesson->type === \App\Enums\LessonType::Video)
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m22 8-6 4 6 4V8Z"></path><rect x="2" y="6" width="14" height="12" rx="2"></rect></svg>
                @elseif ($lesson->type === \App\Enums\LessonType::Text)
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h10"></path></svg>
                @else
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"></path><path d="M14 2v5h5"></path></svg>
                @endif
            </span>

            <span class="flex-1 truncate text-sm text-neutral-800">{{ $lesson->title }}</span>
            @unless ($lesson->is_published)
                <x-badge variant="neutral">Draft</x-badge>
            @endunless
            @if ($lesson->duration_seconds)
                <span class="font-mono text-xs text-neutral-400">{{ intdiv($lesson->duration_seconds, 60) }}m {{ $lesson->duration_seconds % 60 }}s</span>
            @endif

            <button type="button" wire:click="moveLesson({{ $lesson->id }}, -1)" @if ($li === 0) disabled @endif title="Move up" class="flex h-7 w-7 items-center justify-center rounded-sm border border-neutral-200 text-neutral-500 hover:text-teal-700 disabled:opacity-40">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
            </button>
            <button type="button" wire:click="moveLesson({{ $lesson->id }}, 1)" @if ($li === $lessons->count() - 1) disabled @endif title="Move down" class="flex h-7 w-7 items-center justify-center rounded-sm border border-neutral-200 text-neutral-500 hover:text-teal-700 disabled:opacity-40">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <button type="button" wire:click="editLesson({{ $lesson->id }})" class="rounded-sm bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 hover:bg-teal-100">Edit</button>
            <button type="button" wire:click="confirmDelete({{ $lesson->id }})" x-on:click="$dispatch('open-modal', 'delete-lesson-{{ $lesson->id }}')" title="Delete lesson" class="flex h-7 w-7 items-center justify-center rounded-sm border border-neutral-200 text-neutral-500 hover:border-red-200 hover:text-red-600">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </button>

            <livewire:admin.courses.lesson-editor :lesson="$lesson" wire:key="lesson-editor-{{ $lesson->id }}" />

            <x-modal name="delete-lesson-{{ $lesson->id }}" title="Delete lesson">
                <p class="text-sm text-neutral-600">This deletes <span class="font-medium">{{ $lesson->title }}</span> and queues its files for cleanup.</p>
                <x-slot:footer>
                    <x-button x-on:click="$dispatch('close-modal', 'delete-lesson-{{ $lesson->id }}')" variant="secondary" size="sm">Cancel</x-button>
                    <x-button wire:click="delete" x-on:click="$dispatch('close-modal', 'delete-lesson-{{ $lesson->id }}')" variant="danger" size="sm">Delete lesson</x-button>
                </x-slot:footer>
            </x-modal>
        </div>
    @endforeach

    @if ($showForm)
        <div x-data x-init="$dispatch('open-modal', 'lesson-form-{{ $module->id }}')">
            <x-modal name="lesson-form-{{ $module->id }}" title="Add lesson">
                <form wire:submit="save" id="lesson-form-{{ $module->id }}" class="space-y-4">
                    <x-input wire:model="title" label="Lesson title" name="title" required />
                    <x-select wire:model="type" label="Content type" name="type" placeholder="Choose a type" required>
                        @foreach ($selectableTypes as $typeOption)
                            <option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</option>
                        @endforeach
                    </x-select>
                </form>
                <x-slot:footer>
                    <x-button x-on:click="$dispatch('close-modal', 'lesson-form-{{ $module->id }}'); $wire.set('showForm', false)" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" form="lesson-form-{{ $module->id }}" size="sm">Add lesson</x-button>
                </x-slot:footer>
            </x-modal>
        </div>
    @endif

    <button
        type="button"
        wire:click="openCreate"
        class="flex w-full items-center gap-2 px-4 py-2.5 pl-10 text-sm font-semibold text-teal-700 hover:bg-teal-50"
    >
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
        Add lesson
    </button>
</div>
