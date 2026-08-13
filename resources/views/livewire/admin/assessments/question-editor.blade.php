<div>
    <x-modal name="question-editor-{{ $question->id }}" placement="right" title="Edit question">
        <form wire:submit="save" id="question-editor-form-{{ $question->id }}" class="space-y-5">
            <div class="rounded-control bg-neutral-50 px-3 py-2 text-xs text-neutral-500">
                Type: <span class="font-semibold text-neutral-700">{{ ucfirst(str_replace('_', ' ', $question->type->value)) }}</span> — not changeable after creation.
            </div>

            <div>
                <label for="question-body-{{ $question->id }}" class="block text-sm font-medium text-neutral-900">Question text</label>
                <textarea wire:model="body" id="question-body-{{ $question->id }}" rows="3" class="mt-1.5 block w-full rounded-control border border-neutral-200 px-3 py-2 text-sm text-neutral-900 hover:border-neutral-300"></textarea>
                @error('body') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <x-input wire:model="marks" type="number" step="0.01" min="0.01" label="Marks" name="marks" required />
                <x-input wire:model="negative_marks" type="number" step="0.01" min="0" label="Negative marks" name="negative_marks" hint="Applied only if the assessment enables negative marking." />
            </div>

            @include($editorView, ['question' => $question])

            <div>
                <label for="question-explanation-{{ $question->id }}" class="block text-sm font-medium text-neutral-900">Explanation (optional)</label>
                <textarea wire:model="explanation" id="question-explanation-{{ $question->id }}" rows="2" class="mt-1.5 block w-full rounded-control border border-neutral-200 px-3 py-2 text-sm text-neutral-900 hover:border-neutral-300" placeholder="Shown to students on review, where the reveal policy allows it."></textarea>
            </div>
        </form>

        <x-slot:footer>
            <x-button x-on:click="$dispatch('close-modal', 'question-editor-{{ $question->id }}')" variant="ghost" size="sm">Cancel</x-button>
            <x-button type="submit" form="question-editor-form-{{ $question->id }}" size="sm">Save question</x-button>
        </x-slot:footer>
    </x-modal>
</div>
