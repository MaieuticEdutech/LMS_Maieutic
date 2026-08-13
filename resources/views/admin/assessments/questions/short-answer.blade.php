{{--
    Short-answer question editor (App\Services\Assessment\Handlers\ShortAnswerHandler).
    Included inside QuestionEditor's form. Graded by normalised match against
    any accepted answer — offer more than one to cover common variants.
--}}
<div>
    <span class="mb-1.5 block text-sm font-medium text-neutral-900">Accepted answers</span>
    <p class="mb-1.5 text-xs text-neutral-500">Matched case-insensitively, ignoring extra whitespace. List every acceptable phrasing.</p>
    @error('accepted_answers') <p class="mb-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
    <div class="space-y-2">
        @foreach ($accepted_answers as $i => $answer)
            <div class="flex gap-2">
                <input wire:model="accepted_answers.{{ $i }}" type="text" class="block h-10 w-full rounded-control border border-neutral-200 px-3 text-sm text-neutral-900 hover:border-neutral-300" placeholder="Accepted answer">
                <button type="button" wire:click="removeAcceptedAnswer({{ $i }})" class="shrink-0 rounded-control border border-neutral-200 px-2.5 text-neutral-500 hover:border-red-200 hover:text-red-600" aria-label="Remove">&times;</button>
            </div>
        @endforeach
    </div>
    <button type="button" wire:click="addAcceptedAnswer" class="mt-2 text-sm font-semibold text-teal-700 hover:text-teal-800">+ Add accepted answer</button>
</div>
