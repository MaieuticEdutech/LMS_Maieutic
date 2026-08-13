@section('title', 'History — '.$assessment->title.' — '.config('app.name'))

<div class="mx-auto max-w-3xl">
    <p class="eyebrow text-teal-600">{{ $assessment->title }}</p>
    <h1 class="mt-1 text-2xl">Attempt history</h1>

    @if ($attempts->isEmpty())
        <x-empty-state
            title="No attempts yet"
            description="You haven't taken this assessment yet."
            class="mt-6"
        >
            <x-slot:action>
                <x-button :href="route('student.assessments.attempt', $assessment)" wire:navigate>Start assessment</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="mt-6 space-y-3">
            @foreach ($attempts as $attempt)
                <div wire:key="attempt-{{ $attempt->id }}" class="flex items-center gap-4 rounded-card border border-neutral-200 bg-white p-4 {{ $official && $official->is($attempt) ? 'ring-1 ring-teal-600' : '' }}">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-neutral-900">Attempt #{{ $attempt->attempt_number }}</span>
                            @if ($official && $official->is($attempt))
                                <x-badge variant="brand">Official score</x-badge>
                            @endif
                        </div>
                        <div class="text-xs text-neutral-500">
                            Started {{ $attempt->started_at->format('d M Y, H:i') }}
                            @if ($attempt->submitted_at)
                                &middot; submitted {{ $attempt->submitted_at->format('d M Y, H:i') }}
                            @endif
                        </div>
                    </div>

                    @if ($attempt->status === \App\Enums\AttemptStatus::Graded)
                        <div class="text-right">
                            <div class="font-semibold {{ $attempt->is_passed ? 'text-honeydew' : 'text-red-600' }}">{{ $attempt->score_percentage }}%</div>
                            <x-badge :variant="$attempt->is_passed ? 'success' : 'danger'">{{ $attempt->is_passed ? 'Passed' : 'Failed' }}</x-badge>
                        </div>
                        <x-button :href="route('student.assessments.result', $attempt)" variant="secondary" size="sm" wire:navigate>View</x-button>
                    @elseif ($attempt->status === \App\Enums\AttemptStatus::InProgress)
                        <x-badge variant="neutral">In progress</x-badge>
                        <x-button :href="route('student.assessments.attempt', $assessment)" variant="secondary" size="sm" wire:navigate>Continue</x-button>
                    @else
                        <x-badge variant="neutral">{{ ucfirst($attempt->status->value) }}</x-badge>
                    @endif
                </div>
            @endforeach
        </div>

        @if (! $attempts->contains('status', \App\Enums\AttemptStatus::InProgress) && ($assessment->max_attempts === null || $attempts->count() < $assessment->max_attempts))
            <div class="mt-5">
                <x-button :href="route('student.assessments.attempt', $assessment)" wire:navigate>Start a new attempt</x-button>
            </div>
        @endif
    @endif
</div>
