@section('title', 'Result — '.$assessment->title.' — '.config('app.name'))

{{-- Same 760px column and gutters as the runner and the history screen — see
     attempt-history.blade.php for why each states its own. --}}
<div class="mx-auto w-full max-w-[760px] px-5 pb-24 pt-12 lg:px-10">
    <a href="{{ route('student.assessments.history', $assessment) }}" wire:navigate class="mb-5 inline-flex items-center gap-1.5 text-sm font-semibold text-neutral-700 hover:text-teal-700">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
        Attempt history
    </a>

    <div class="rounded-card border border-neutral-200 bg-white p-8 text-center">
        <p class="eyebrow text-teal-600">{{ $assessment->title }}</p>

        @if ($attempt->status === \App\Enums\AttemptStatus::Graded)
            <div class="mt-3 font-serif text-5xl font-semibold {{ $attempt->is_passed ? 'text-honeydew' : 'text-red-600' }}">
                {{ $attempt->score_percentage }}%
            </div>
            <p class="mt-2 text-sm text-neutral-600">
                {{ $attempt->score_marks }} / {{ $attempt->max_marks }} marks &middot;
                <x-badge :variant="$attempt->is_passed ? 'success' : 'danger'">{{ $attempt->is_passed ? 'Passed' : 'Failed' }}</x-badge>
            </p>
            <p class="mt-1 text-xs text-neutral-500">Passing mark: {{ $assessment->passing_percentage }}%</p>
        @else
            <p class="mt-3 text-neutral-600">This attempt is still being processed.</p>
        @endif
    </div>

    @if ($mayReveal)
        <h2 class="mt-8 font-serif text-[26px] font-medium tracking-[-0.01em]">Review</h2>
        <div class="mt-3 space-y-3">
            @foreach ($review as $item)
                <div class="rounded-card border border-neutral-200 bg-white p-5">
                    <div class="mb-2 flex items-start justify-between gap-3">
                        <p class="text-sm font-medium text-neutral-900">{{ $item['question']['body'] }}</p>
                        @if ($item['isCorrect'] !== null)
                            <x-badge :variant="$item['isCorrect'] ? 'success' : 'danger'">
                                {{ $item['isCorrect'] ? 'Correct' : 'Incorrect' }}
                            </x-badge>
                        @endif
                    </div>

                    @if (isset($item['question']['options']))
                        <ul class="space-y-1.5">
                            @foreach ($item['question']['options'] as $option)
                                <li class="flex items-center gap-2 text-sm {{ $option['is_correct'] ?? false ? 'font-medium text-honeydew' : 'text-neutral-600' }}">
                                    @if ($option['is_correct'] ?? false)
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
                                    @endif
                                    {{ $option['body'] }}
                                </li>
                            @endforeach
                        </ul>
                    @elseif (isset($item['question']['accepted_answers']))
                        <p class="text-sm text-neutral-600">
                            Accepted: {{ implode(', ', $item['question']['accepted_answers']) }}
                        </p>
                    @endif

                    @if ($item['question']['explanation'] ?? null)
                        <p class="mt-2 text-xs text-neutral-500">{{ $item['question']['explanation'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
