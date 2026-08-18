<div class="space-y-2" x-data="{
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

    @php $questionIds = $questions->pluck('id')->all(); @endphp

    @forelse ($questions as $qi => $question)
        <div
            wire:key="question-{{ $question->id }}"
            x-on:dragover.prevent
            x-on:drop.prevent="onDrop(@js($questionIds), {{ $qi }})"
            class="flex items-center gap-3 rounded-md border border-neutral-200 bg-white px-4 py-3"
        >
            <span draggable="true" x-on:dragstart="dragIndex = {{ $qi }}" class="cursor-grab text-neutral-400" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="6" r="1"></circle><circle cx="15" cy="6" r="1"></circle><circle cx="9" cy="12" r="1"></circle><circle cx="15" cy="12" r="1"></circle><circle cx="9" cy="18" r="1"></circle><circle cx="15" cy="18" r="1"></circle></svg>
            </span>
            <span class="font-mono text-xs text-neutral-500">{{ str_pad((string) ($qi + 1), 2, '0', STR_PAD_LEFT) }}</span>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-medium text-neutral-900">{{ $question->body }}</div>
                <div class="text-xs text-neutral-500">{{ $questionTypes[$question->type->value]->label() ?? $question->type->value }} &middot; {{ $question->marks }} {{ Str::plural('mark', (float) $question->marks) }}</div>
            </div>

            <button type="button" wire:click="moveQuestion({{ $question->id }}, -1)" @if ($qi === 0) disabled @endif title="Move up" class="flex h-7 w-7 items-center justify-center rounded-sm border border-neutral-200 text-neutral-500 hover:text-teal-700 disabled:opacity-40">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
            </button>
            <button type="button" wire:click="moveQuestion({{ $question->id }}, 1)" @if ($qi === $questions->count() - 1) disabled @endif title="Move down" class="flex h-7 w-7 items-center justify-center rounded-sm border border-neutral-200 text-neutral-500 hover:text-teal-700 disabled:opacity-40">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <button type="button" x-on:click="$dispatch('open-modal', 'question-editor-{{ $question->id }}')" class="rounded-sm bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-700 hover:bg-teal-100">Edit</button>
            <button type="button" wire:click="confirmDelete({{ $question->id }})" x-on:click="$dispatch('open-modal', 'delete-question-{{ $question->id }}')" title="Delete question" class="flex h-7 w-7 items-center justify-center rounded-sm border border-neutral-200 text-neutral-500 hover:border-red-200 hover:text-red-600">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </button>

            <livewire:admin.assessments.question-editor :question="$question" wire:key="question-editor-{{ $question->id }}" />

            <x-modal name="delete-question-{{ $question->id }}" title="Delete question">
                <p class="text-sm text-neutral-600">This deletes question <span class="font-medium">&ldquo;{{ Str::limit($question->body, 60) }}&rdquo;</span>.</p>
                <x-slot:footer>
                    <x-button x-on:click="$dispatch('close-modal', 'delete-question-{{ $question->id }}')" variant="secondary" size="sm">Cancel</x-button>
                    <x-button wire:click="delete" x-on:click="$dispatch('close-modal', 'delete-question-{{ $question->id }}')" variant="danger" size="sm">Delete question</x-button>
                </x-slot:footer>
            </x-modal>
        </div>
    @empty
        <x-empty-state title="No questions yet" description="Add a question to start building this assessment." />
    @endforelse

    @if ($showForm)
        {{--
            $nextTick is required, not decorative.

            Alpine initialises a parent before its children, so an x-init on
            this wrapper runs BEFORE the <x-modal> inside it has registered
            its `x-on:open-modal.window` listener. Dispatching immediately
            sends the event into the void: `showForm` flips true, the markup
            enters the DOM, and the dialog never appears — no error anywhere,
            because nothing failed. It simply was not listening yet.

            Deferring by one tick lets the children initialise first.

            The same bug shipped three times, on Add module, Add lesson and
            here. If a fourth modal is ever opened this way, copy the working
            form below rather than the intuitive one.
        --}}
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'question-form'))">
            <x-modal name="question-form" title="Add question">
                <form wire:submit="save" id="question-form" class="space-y-4">
                    <x-select wire:model="type" label="Question type" name="type" placeholder="Choose a type" required>
                        @foreach ($questionTypes as $typeOption)
                            <option value="{{ $typeOption->value }}">{{ ucfirst(str_replace('_', ' ', $typeOption->value)) }}</option>
                        @endforeach
                    </x-select>
                    <p class="text-xs text-neutral-500">You'll fill in the question text, marks and answer key next.</p>
                </form>
                <x-slot:footer>
                    <x-button x-on:click="$dispatch('close-modal', 'question-form'); $wire.set('showForm', false)" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" form="question-form" size="sm">Add question</x-button>
                </x-slot:footer>
            </x-modal>
        </div>
    @endif

    <div class="flex items-center gap-2">
        <button
            type="button"
            wire:click="openCreate"
            class="flex flex-1 items-center justify-center gap-2 rounded-md border-2 border-dashed border-neutral-300 p-3.5 text-sm font-semibold text-teal-700 hover:border-teal-400 hover:bg-teal-50"
        >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
            Add question
        </button>

        {{-- Bulk import sits beside "Add question" rather than replacing it:
             one-off authoring and loading a prepared bank are different jobs,
             and an author doing the first should not have to find their way
             past the second. --}}
        <livewire:admin.assessments.question-import
            :assessment="$assessment"
            wire:key="question-import-{{ $assessment->id }}"
        />
    </div>
</div>
