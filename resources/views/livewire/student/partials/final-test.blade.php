@props(['assessment', 'state', 'course', 'variant' => 'rail'])
{{--
    The course-level FINAL TEST, reachable from the player (FR-STU-10).

    Until this existed the only ways into an assessment were a quiz lesson and
    the attempt history page, so a student had no way to find a course's final
    test — and a course that REQUIRES one could never be completed (AC-31).

    Starting is still decided entirely by StartAttempt (access, availability,
    attempts left); this only offers the way in.
--}}
@php
    $passed = $state['passed'];
    $started = $state['attempts'] > 0 || $state['inProgress'];
    $label = $state['inProgress'] ? 'Continue final test' : ($started ? 'Retake final test' : 'Start final test');
@endphp

@if ($variant === 'card')
    <section data-final-test-card aria-labelledby="final-test-heading"
             class="rounded-hero bg-teal-900 p-7 text-white">
        <p class="eyebrow text-white/55">All lessons complete</p>
        <h2 id="final-test-heading" class="mt-2 font-serif text-xl font-semibold text-balance text-white">
            One last step: the final test
        </h2>
        <p class="mt-1 max-w-[60ch] text-sm text-white/75">
            Pass “{{ $assessment->title }}” to complete {{ $course->title }} and receive your certificate.
        </p>
        <div class="mt-4 flex flex-wrap gap-2.5">
            <x-button :href="route('student.assessments.attempt', $assessment)" size="lg" pill wire:navigate>{{ $label }}</x-button>
            @if ($started)
                <x-button :href="route('student.assessments.history', $assessment)" variant="secondary" size="lg" pill wire:navigate>Attempt history</x-button>
            @endif
        </div>
    </section>
@else
    <div data-final-test class="border-t border-neutral-200 px-[22px] py-5">
        <p class="mb-2 font-mono text-[11px] font-semibold tracking-[0.14em] text-neutral-700">FINAL TEST</p>
        <p class="text-[13.5px] font-medium text-neutral-900">{{ $assessment->title }}</p>

        <p class="mt-1 text-[12.5px] text-neutral-500">
            @if ($passed)
                <x-badge variant="success">Passed</x-badge>
            @elseif ($state['inProgress'])
                Attempt in progress.
            @elseif ($state['attempts'] > 0)
                {{ $state['attempts'] }} {{ Str::plural('attempt', $state['attempts']) }}, not passed yet.
            @else
                Not attempted yet.
            @endif
        </p>

        <div class="mt-3 flex flex-wrap gap-2">
            @unless ($passed)
                <x-button :href="route('student.assessments.attempt', $assessment)" size="sm" wire:navigate>{{ $label }}</x-button>
            @endunless
            @if ($started)
                <x-button :href="route('student.assessments.history', $assessment)" variant="secondary" size="sm" wire:navigate>Attempt history</x-button>
            @endif
        </div>
    </div>
@endif
