@section('title', $assessment->title.' — '.app(\App\Services\Settings\BrandingService::class)->organisationName())

{{--
    Sitting an assessment (design handoff §5).

    A 760px column: eyebrow, serif H1, progress bar over "Question N of M", one
    question card at a time, and a footer with Previous on the left, Save & exit
    and Next question on the right.

    ═════════════════════════════════════════════════════════════════════════
    PAGING IS SAFE HERE ONLY BECAUSE ANSWERS ALREADY PERSIST ON CHANGE.

    Each input calls saveAnswer(), which writes through the SaveAnswer action
    immediately. So moving between questions cannot lose work, the timer can
    submit the whole attempt whenever it expires regardless of which question is
    showing, and closing the tab on question 7 loses nothing.

    The progress bar counts ANSWERED questions, not position. "Question 4 of 10"
    tells a student where they are; the bar tells them what is left to do, and
    those are different facts — someone who skipped two questions needs to know.
    ═════════════════════════════════════════════════════════════════════════

    The last question shows "Submit assessment" in place of "Next question",
    which still opens the confirmation modal rather than submitting outright.
--}}
@php
    $total = $questions->count();
    $isLast = $current !== null && $index >= $total - 1;
@endphp

<div class="mx-auto w-full max-w-[760px] px-5 pb-24 pt-12 lg:px-10">

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

    @if ($assessment->instructions)
        <x-alert variant="info" class="mb-6">{{ $assessment->instructions }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert variant="danger" class="mb-6">{{ session('error') }}</x-alert>
    @endif

    @if ($current === null)
        <x-empty-state
            title="This assessment has no questions yet"
            description="Nothing can be answered until an instructor adds them."
        />
    @else
        <div class="mb-8 flex items-center gap-3">
            <x-progress-bar
                :value="$total > 0 ? (int) round($answeredCount / $total * 100) : 0"
                label="Questions answered"
                class="flex-1"
            />
            <span class="font-mono text-[12.5px] font-semibold text-neutral-700">
                Question {{ $index + 1 }} of {{ $total }}
            </span>
        </div>

        {{-- wire:key on the question id, not the index: keying on the index
             would let Livewire reuse the previous question's DOM — and its
             checked radio — for the next one. --}}
        <div class="rounded-card border border-neutral-200 bg-white p-8" wire:key="runner-question-{{ $current['id'] }}">

            <div class="mb-4 flex items-baseline justify-between gap-3">
                <span class="font-mono text-[12.5px] font-semibold text-neutral-700">
                    {{ $current['marks'] }} {{ Str::plural('mark', $current['marks']) }}
                </span>

                @if ($answeredCount < $total)
                    <span class="text-xs text-neutral-500">{{ $total - $answeredCount }} still to answer</span>
                @else
                    <span class="text-xs text-honeydew">All questions answered</span>
                @endif
            </div>

            <h2 class="mb-6 font-serif text-xl/[1.4] font-medium">{{ $current['body'] }}</h2>

            @if ($current['type'] === 'single_choice' || $current['type'] === 'true_false')
                <div class="flex flex-col gap-2.5">
                    @foreach ($current['options'] as $option)
                        {{-- A REAL radio, visually hidden, driving the styling
                             through `peer-checked`. Painting divs would mean
                             rebuilding keyboard support and the accessibility
                             tree by hand, and getting one of them wrong. --}}
                        <label class="flex cursor-pointer items-center gap-3.5 rounded-control border-[1.5px] border-neutral-200 bg-white px-[18px] py-[15px] transition-all duration-150 has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50 hover:border-teal-200">
                            <input
                                type="radio"
                                name="answer-{{ $current['id'] }}"
                                wire:model="answers.{{ $current['id'] }}"
                                value="{{ $option['id'] }}"
                                wire:change="saveAnswer({{ $current['id'] }})"
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
            @elseif ($current['type'] === 'multiple_choice')
                <div class="flex flex-col gap-2.5">
                    @foreach ($current['options'] as $option)
                        {{-- Square, not round: the shape is the only thing
                             telling a student "more than one of these". --}}
                        <label class="flex cursor-pointer items-center gap-3.5 rounded-control border-[1.5px] border-neutral-200 bg-white px-[18px] py-[15px] transition-all duration-150 has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50 hover:border-teal-200">
                            <input
                                type="checkbox"
                                wire:model="answers.{{ $current['id'] }}"
                                value="{{ $option['id'] }}"
                                wire:change="saveAnswer({{ $current['id'] }})"
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
                    wire:model="answers.{{ $current['id'] }}"
                    wire:change="saveAnswer({{ $current['id'] }})"
                    class="block h-11 w-full rounded-control border border-neutral-300 px-3.5 text-[15px] text-neutral-900 hover:border-neutral-400"
                    placeholder="Your answer"
                    aria-label="Your answer"
                >
            @endif
        </div>

        {{-- ══ FOOTER ══ Previous left; Save & exit + Next/Submit right. --}}
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3" x-data>
            <div>
                @if ($index > 0)
                    <button type="button"
                            wire:click="goToQuestion({{ $index - 1 }})"
                            class="rounded-sm border border-neutral-300 bg-white px-5 py-[11px] text-sm font-semibold text-neutral-700 transition-colors hover:bg-neutral-50">
                        Previous
                    </button>
                @endif
            </div>

            <div class="flex flex-wrap gap-2.5">
                {{-- Saves nothing new — every answer is already persisted. It is
                     a way OUT, and the attempt stays in progress. --}}
                <button type="button"
                        wire:click="saveAndExit"
                        class="rounded-sm border border-neutral-300 bg-white px-5 py-[11px] text-sm font-semibold text-neutral-700 transition-colors hover:bg-neutral-50">
                    Save &amp; exit
                </button>

                @if ($isLast)
                    {{-- Still opens the confirmation rather than submitting
                         outright: grading is irreversible. --}}
                    <button type="button"
                            x-on:click="$dispatch('open-modal', 'confirm-submit')"
                            class="rounded-sm bg-teal-600 px-6 py-[11px] text-sm font-semibold text-white transition-colors hover:bg-teal-700">
                        Submit assessment
                    </button>
                @else
                    <button type="button"
                            wire:click="goToQuestion({{ $index + 1 }})"
                            class="rounded-sm bg-teal-600 px-6 py-[11px] text-sm font-semibold text-white transition-colors hover:bg-teal-700">
                        Next question
                    </button>
                @endif
            </div>
        </div>

        <x-modal name="confirm-submit" title="Submit this assessment?">
            <p class="text-sm text-neutral-600">
                Once submitted you cannot change your answers. Unanswered questions score zero.
            </p>

            @if ($answeredCount < $total)
                {{-- Named before the point of no return. "Unanswered questions
                     score zero" is abstract; "3 of 10 unanswered" is not. --}}
                <p class="mt-3 text-sm font-semibold text-red-600">
                    {{ $total - $answeredCount }} of {{ $total }} {{ Str::plural('question', $total) }} not yet answered.
                </p>
            @endif

            <x-slot:footer>
                <x-button x-on:click="$dispatch('close-modal', 'confirm-submit')" variant="secondary" size="sm">Keep working</x-button>
                <x-button wire:click="submit" variant="primary" size="sm">Submit</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
