{{--
    Single-choice question editor (App\Services\Assessment\Handlers\SingleChoiceHandler).
    Included inside QuestionEditor's form — wire:model targets bind to that
    parent component's public $options array.
--}}
<div>
    <span class="mb-1.5 block text-sm font-medium text-neutral-900">Options — mark exactly one correct</span>
    @error('options') <p class="mb-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
    <div class="space-y-2">
        @foreach ($options as $i => $option)
            <div class="flex items-center gap-2">
                <input
                    type="radio"
                    name="correct-option-{{ $question->id }}"
                    wire:click="markCorrectOption({{ $i }})"
                    @checked($option['is_correct'])
                    class="size-4 shrink-0 border-neutral-300 text-teal-600"
                    aria-label="Mark option {{ $i + 1 }} correct"
                >
                <input wire:model="options.{{ $i }}.body" type="text" class="block h-10 w-full rounded-control border border-neutral-200 px-3 text-sm text-neutral-900 hover:border-neutral-300" placeholder="Option {{ $i + 1 }}">
                <button type="button" wire:click="removeOption({{ $i }})" class="shrink-0 rounded-control border border-neutral-200 px-2.5 text-neutral-500 hover:border-red-200 hover:text-red-600" aria-label="Remove option">&times;</button>
            </div>
        @endforeach
    </div>
    <button type="button" wire:click="addOption" class="mt-2 text-sm font-semibold text-teal-700 hover:text-teal-800">+ Add option</button>
</div>
