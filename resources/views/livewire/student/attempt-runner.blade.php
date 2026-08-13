@section('title', $assessment->title.' — '.config('app.name'))

<div class="mx-auto max-w-3xl">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="eyebrow text-teal-600">{{ ucfirst($assessment->type->value) }}</p>
            <h1 class="mt-1 text-2xl">{{ $assessment->title }}</h1>
        </div>

        @if ($attempt->expires_at)
            <div
                x-data="{
                    remaining: Math.max(0, Math.floor((new Date('{{ $attempt->expires_at->toIso8601String() }}') - new Date()) / 1000)),
                    tick() {
                        this.remaining = Math.max(0, this.remaining - 1);
                        if (this.remaining === 0) { $wire.submit(); }
                    },
                    label() {
                        const m = Math.floor(this.remaining / 60);
                        const s = this.remaining % 60;
                        return m + 'm ' + String(s).padStart(2, '0') + 's';
                    },
                }"
                x-init="setInterval(() => tick(), 1000)"
                class="rounded-control border border-neutral-200 bg-white px-4 py-2 text-center"
            >
                <div class="eyebrow text-neutral-500">Time remaining</div>
                <div class="font-mono text-lg font-semibold text-neutral-900" x-text="label()"></div>
            </div>
        @endif
    </div>

    @if ($assessment->instructions)
        <x-alert variant="info" class="mb-6">{{ $assessment->instructions }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert variant="danger" class="mb-6">{{ session('error') }}</x-alert>
    @endif

    <div class="space-y-4">
        @foreach ($questions as $i => $question)
            <div class="rounded-card border border-neutral-200 bg-white p-5" wire:key="runner-question-{{ $question['id'] }}">
                <div class="mb-3 flex items-baseline justify-between gap-3">
                    <span class="font-mono text-xs text-neutral-500">Question {{ $i + 1 }} of {{ $questions->count() }}</span>
                    <span class="text-xs text-neutral-500">{{ $question['marks'] }} {{ Str::plural('mark', $question['marks']) }}</span>
                </div>
                <p class="mb-4 text-base text-neutral-900">{{ $question['body'] }}</p>

                @if ($question['type'] === 'single_choice' || $question['type'] === 'true_false')
                    <div class="space-y-2">
                        @foreach ($question['options'] as $option)
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-control border border-neutral-200 px-3.5 py-2.5 hover:border-neutral-300">
                                <input
                                    type="radio"
                                    name="answer-{{ $question['id'] }}"
                                    wire:model="answers.{{ $question['id'] }}"
                                    value="{{ $option['id'] }}"
                                    wire:change="saveAnswer({{ $question['id'] }})"
                                    class="size-4 shrink-0 border-neutral-300 text-teal-600"
                                >
                                <span class="text-sm text-neutral-800">{{ $option['body'] }}</span>
                            </label>
                        @endforeach
                    </div>
                @elseif ($question['type'] === 'multiple_choice')
                    <div class="space-y-2">
                        @foreach ($question['options'] as $option)
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-control border border-neutral-200 px-3.5 py-2.5 hover:border-neutral-300">
                                <input
                                    type="checkbox"
                                    wire:model="answers.{{ $question['id'] }}"
                                    value="{{ $option['id'] }}"
                                    wire:change="saveAnswer({{ $question['id'] }})"
                                    class="size-4 shrink-0 rounded-xs border-neutral-300 text-teal-600"
                                >
                                <span class="text-sm text-neutral-800">{{ $option['body'] }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <input
                        type="text"
                        wire:model="answers.{{ $question['id'] }}"
                        wire:change="saveAnswer({{ $question['id'] }})"
                        class="block h-10 w-full rounded-control border border-neutral-200 px-3 text-sm text-neutral-900 hover:border-neutral-300"
                        placeholder="Your answer"
                    >
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex justify-end" x-data>
        <x-button x-on:click="$dispatch('open-modal', 'confirm-submit')" size="lg">Submit assessment</x-button>
    </div>

    <x-modal name="confirm-submit" title="Submit this assessment?">
        <p class="text-sm text-neutral-600">
            Once submitted you cannot change your answers. Unanswered questions score zero.
        </p>
        <x-slot:footer>
            <x-button x-on:click="$dispatch('close-modal', 'confirm-submit')" variant="secondary" size="sm">Keep working</x-button>
            <x-button wire:click="submit" variant="primary" size="sm">Submit</x-button>
        </x-slot:footer>
    </x-modal>
</div>
