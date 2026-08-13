{{--
    True/false question editor (App\Services\Assessment\Handlers\TrueFalseHandler).
    Included inside QuestionEditor's form. Exactly two fixed options — no
    add/remove, unlike single/multiple choice.
--}}
<div>
    <span class="mb-1.5 block text-sm font-medium text-neutral-900">Correct answer</span>
    @error('options') <p class="mb-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
    <div class="flex gap-3">
        @foreach ($options as $i => $option)
            <label class="flex flex-1 cursor-pointer items-center gap-2 rounded-control border px-3.5 py-2.5 {{ $option['is_correct'] ? 'border-teal-600 bg-teal-50' : 'border-neutral-200' }}">
                <input
                    type="radio"
                    name="correct-option-{{ $question->id }}"
                    wire:click="markCorrectOption({{ $i }})"
                    @checked($option['is_correct'])
                    class="size-4 shrink-0 border-neutral-300 text-teal-600"
                >
                <span class="text-sm font-medium text-neutral-900">{{ $option['body'] }}</span>
            </label>
        @endforeach
    </div>
</div>
