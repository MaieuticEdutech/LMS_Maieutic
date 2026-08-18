<div class="space-y-3" x-data="{
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

    {{-- A refusal the component itself has to show.

         These actions flashed to the session, and both layouts do render
         session('error') — but Livewire re-renders the COMPONENT, not the
         surrounding layout, so the message landed in a region that was never
         redrawn. The row simply stayed put and the control looked broken. --}}
    @error('action')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    @php $moduleIds = $modules->pluck('id')->all(); @endphp

    @forelse ($modules as $mi => $module)
        <div
            wire:key="module-{{ $module->id }}"
            x-on:dragover.prevent
            x-on:drop.prevent="onDrop(@js($moduleIds), {{ $mi }})"
            class="overflow-hidden rounded-md border border-neutral-200 bg-white"
        >
            <div class="flex items-center gap-2.5 border-b border-neutral-200 bg-neutral-50 px-4 py-3">
                <span
                    draggable="true"
                    x-on:dragstart="dragIndex = {{ $mi }}"
                    class="cursor-grab text-neutral-400"
                    aria-hidden="true"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="6" r="1"></circle><circle cx="15" cy="6" r="1"></circle><circle cx="9" cy="12" r="1"></circle><circle cx="15" cy="12" r="1"></circle><circle cx="9" cy="18" r="1"></circle><circle cx="15" cy="18" r="1"></circle></svg>
                </span>
                <span class="font-mono text-xs text-neutral-500">{{ str_pad((string) ($mi + 1), 2, '0', STR_PAD_LEFT) }}</span>
                <span class="flex-1 truncate font-semibold text-neutral-900">{{ $module->title }}</span>
                @unless ($module->is_published)
                    <x-badge variant="neutral">Draft</x-badge>
                @endunless
                <span class="text-xs text-neutral-500">{{ $module->lessons_count }} {{ Str::plural('lesson', $module->lessons_count) }}</span>

                <button type="button" wire:click="moveModule({{ $module->id }}, -1)" @if ($mi === 0) disabled @endif title="Move up" class="flex h-7 w-7 items-center justify-center rounded-sm border border-neutral-200 text-neutral-500 hover:text-teal-700 disabled:opacity-40">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
                </button>
                <button type="button" wire:click="moveModule({{ $module->id }}, 1)" @if ($mi === $modules->count() - 1) disabled @endif title="Move down" class="flex h-7 w-7 items-center justify-center rounded-sm border border-neutral-200 text-neutral-500 hover:text-teal-700 disabled:opacity-40">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
                </button>
                <button type="button" wire:click="openEdit({{ $module->id }})" title="Edit module" class="flex h-7 w-7 items-center justify-center rounded-sm border border-neutral-200 text-neutral-500 hover:text-teal-700">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"></path></svg>
                </button>
                <button type="button" wire:click="confirmDelete({{ $module->id }})" x-on:click="$dispatch('open-modal', 'delete-module-{{ $module->id }}')" title="Delete module" class="flex h-7 w-7 items-center justify-center rounded-sm border border-neutral-200 text-neutral-500 hover:border-red-200 hover:text-red-600">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                </button>
            </div>

            <livewire:admin.courses.lesson-list :module="$module" wire:key="lessons-list-{{ $module->id }}" />

            <x-modal name="delete-module-{{ $module->id }}" title="Delete module">
                <p class="text-sm text-neutral-600">
                    This deletes <span class="font-medium">{{ $module->title }}</span> and its
                    {{ $module->lessons_count }} {{ Str::plural('lesson', $module->lessons_count) }} inside it.
                </p>
                <x-slot:footer>
                    <x-button x-on:click="$dispatch('close-modal', 'delete-module-{{ $module->id }}')" variant="secondary" size="sm">Cancel</x-button>
                    <x-button wire:click="delete" x-on:click="$dispatch('close-modal', 'delete-module-{{ $module->id }}')" variant="danger" size="sm">Delete module</x-button>
                </x-slot:footer>
            </x-modal>
        </div>
    @empty
        <x-empty-state title="No modules yet" description="Add a module to start building the curriculum." />
    @endforelse

    @if ($showForm)
        {{-- $nextTick is load-bearing, not defensive.

             Alpine initialises parents before children, so x-init here runs
             BEFORE the <x-modal> nested inside binds its
             `x-on:open-modal.window` listener. Dispatching immediately fires
             the event into nothing and the modal never opens — which is
             exactly what happened: the button worked, the component set
             showForm, this markup rendered, and no dialog appeared.

             Deferring to the next tick lets the whole subtree initialise
             first, so the listener exists by the time the event is sent. --}}
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'module-form'))">
            <x-modal name="module-form" :title="$editing ? 'Edit module' : 'Add module'">
                <form wire:submit="save" id="module-form" class="space-y-4">
                    <x-input wire:model="title" label="Module title" name="title" required />
                    <div>
                        <label for="module-description" class="block text-sm font-medium text-neutral-900">Description (optional)</label>
                        <textarea wire:model="description" id="module-description" rows="3" class="mt-1.5 block w-full rounded-control border border-neutral-200 px-3 py-2 text-sm text-neutral-900 hover:border-neutral-300"></textarea>
                    </div>
                    <x-checkbox wire:model="is_published" label="Published — visible to enrolled students" name="is_published" />
                </form>
                <x-slot:footer>
                    <x-button x-on:click="$dispatch('close-modal', 'module-form'); $wire.set('showForm', false)" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" form="module-form" size="sm">{{ $editing ? 'Save module' : 'Add module' }}</x-button>
                </x-slot:footer>
            </x-modal>
        </div>
    @endif

    <button
        type="button"
        wire:click="openCreate"
        class="flex w-full items-center justify-center gap-2 rounded-md border-2 border-dashed border-neutral-300 p-3.5 text-sm font-semibold text-teal-700 hover:border-teal-400 hover:bg-teal-50"
    >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
        Add module
    </button>
</div>
