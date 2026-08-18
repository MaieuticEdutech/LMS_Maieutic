@section('title', $assessment->title.' — '.app(\App\Services\Settings\BrandingService::class)->organisationName())

{{--
    Sitting an assessment.

    The mockup's quiz screen: a narrow 760px column, an eyebrow and serif title,
    a progress bar over "Question 4 of 10", and answer options as large bordered
    targets with a custom radio dot.

    ═════════════════════════════════════════════════════════════════════════
    ONE DIFFERENCE FROM THE MOCKUP, DELIBERATELY: EVERY QUESTION IS ON THE PAGE.

    The mockup pages one question at a time with Previous / Next. That is not a
    styling choice — it is a different way to sit an exam, and this runner is
    built around the other one: answers autosave per question, the timer submits
    the whole attempt when it expires, and a student can review everything
    before committing. Paging would also mean a server round trip between
    questions, which is the last thing wanted on a timed test with a weak
    connection.

    So the mockup's LOOK is applied to each question, and the interaction model
    is unchanged. Say the word if paging is genuinely wanted — it is a real
    change to how assessments work, not a stylesheet.
    ═════════════════════════════════════════════════════════════════════════

    The bar counts ANSWERED questions rather than position on the page, which is
    the fact a student actually wants before they submit.
--}}
@php
    $answered = collect($questions)->filter(function (array $question) {
        $given = $answers[$question['id']] ?? null;

        return is_array($given) ? $given !== [] : ($given !== null && $given !== '');
    })->count();
@endphp

<div class="mx-auto w-full max-w-[760px] px-5 pb-24 pt-12 lg:px-10">

    {{-- A refusal the component itself has to show.

         These actions flashed to the session, and both layouts do render
         session('error') — but Livewire re-renders the COMPONENT, not the
         surrounding layout, so the message landed in a region that was never
         redrawn. The row simply stayed put and the control looked broken. --}}
    @error('action')
        <x-alert variant="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror


    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="eyebrow text-teal-600">{{ ucfirst($assessment->type->value) }}</p>
            <h1 class="mt-2 font-serif text-[30px] font-medium tracking-[-0.01em]">{{ $assessment->title }}</h1>
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

    {{-- Progress across the whole attempt. Labelled, because it is the only
         statement of the figure — nothing else on screen says how many are
         answered. --}}
    <div class="mb-8 flex items-center gap-3">
        <x-progress-bar
            :value="$questions->count() > 0 ? (int) round($answered / $questions->count() * 100) : 0"
            label="Questions answered"
            class="flex-1"
        />
        <span class="font-mono text-[12.5px] font-semibold text-neutral-700">
            {{ $answered }} of {{ $questions->count() }} answered
        </span>
    </div>

    @if ($assessment->instructions)
        <x-alert variant="info" class="mb-6">{{ $assessment->instructions }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert variant="danger" class="mb-6">{{ session('error') }}</x-alert>
    @endif

    <div class="space-y-5">
        @foreach ($questions as $i => $question)
            <div class="rounded-card border border-neutral-200 bg-white p-8" wire:key="runner-question-{{ $question['id'] }}">

                <div class="mb-4 flex items-baseline justify-between gap-3">
                    <span class="font-mono text-[12.5px] font-semibold text-neutral-700">
                        Question {{ $i + 1 }} of {{ $questions->count() }}
                    </span>
                    <span class="text-xs text-neutral-500">
                        {{ $question['marks'] }} {{ Str::plural('mark', $question['marks']) }}
                    </span>
                </div>

                <h2 class="mb-6 font-serif text-xl/[1.4] font-medium">{{ $question['body'] }}</h2>

                @if ($question['type'] === 'single_choice' || $question['type'] === 'true_false')
                    <div class="flex flex-col gap-2.5">
                        @foreach ($question['options'] as $option)
                            {{-- A REAL radio, visually hidden, driving the styling
                                 through `peer-checked`. Painting divs would mean
                                 rebuilding keyboard support and the accessibility
                                 tree by hand, and getting one of them wrong. --}}
                            <label class="group flex cursor-pointer items-center gap-3.5 rounded-control border-[1.5px] border-neutral-200 bg-white px-[18px] py-[15px] transition-all duration-150 has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50 hover:border-teal-200">
                                <input
                                    type="radio"
                                    name="answer-{{ $question['id'] }}"
                                    wire:model="answers.{{ $question['id'] }}"
                                    value="{{ $option['id'] }}"
                                    wire:change="saveAnswer({{ $question['id'] }})"
                                    class="peer sr-only"
                                >

                                <span class="flex size-[18px] shrink-0 items-center justify-center rounded-full border-2 border-neutral-400 transition-colors peer-checked:border-teal-600"
                                      aria-hidden="true">
                                    <span class="size-2 rounded-full bg-transparent transition-colors peer-checked:bg-teal-600"></span>
                                </span>

                                <span class="text-[15px] text-neutral-800">{{ $option['body'] }}</span>
                            </label>
                        @endforeach
                    </div>
                @elseif ($question['type'] === 'multiple_choice')
                    <div class="flex flex-col gap-2.5">
                        @foreach ($question['options'] as $option)
                            {{-- Square, not round: the shape is the only thing
                                 telling a student "more than one of these". --}}
                            <label class="group flex cursor-pointer items-center gap-3.5 rounded-control border-[1.5px] border-neutral-200 bg-white px-[18px] py-[15px] transition-all duration-150 has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50 hover:border-teal-200">
                                <input
                                    type="checkbox"
                                    wire:model="answers.{{ $question['id'] }}"
                                    value="{{ $option['id'] }}"
                                    wire:change="saveAnswer({{ $question['id'] }})"
                                    class="peer sr-only"
                                >

                                <span class="flex size-[18px] shrink-0 items-center justify-center rounded-xs border-2 border-neutral-400 transition-colors peer-checked:border-teal-600 peer-checked:bg-teal-600"
                                      aria-hidden="true">
                                    <svg class="size-2.5 text-white opacity-0 transition-opacity peer-checked:opacity-100"
                                         viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M2 5.5L4 7.5L8 3" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>

                                <span class="text-[15px] text-neutral-800">{{ $option['body'] }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <input
                        type="text"
                        wire:model="answers.{{ $question['id'] }}"
                        wire:change="saveAnswer({{ $question['id'] }})"
                        class="block h-11 w-full rounded-control border border-neutral-300 px-3.5 text-[15px] text-neutral-900 hover:border-neutral-400"
                        placeholder="Your answer"
                        aria-label="Your answer"
                    >
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex justify-end" x-data>
        <button type="button"
                x-on:click="$dispatch('open-modal', 'confirm-submit')"
                class="rounded-sm bg-teal-600 px-6 py-3 text-[15px] font-semibold text-white transition-colors hover:bg-teal-700">
            Submit assessment
        </button>
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
