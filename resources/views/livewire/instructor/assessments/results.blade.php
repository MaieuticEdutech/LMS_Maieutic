<div>
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route($indexRoute) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold text-neutral-700 hover:text-teal-700">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
            Assessments
        </a>
        <span class="text-neutral-300" aria-hidden="true">/</span>
        <h1 class="text-2xl">{{ $assessment->title }}</h1>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-tile label="Attempts" :value="$statistics['attemptCount']" />
        <x-stat-tile label="Average score" :value="$statistics['averageScorePercentage'].'%'" />
        <x-stat-tile label="Pass rate" :value="$statistics['passRate'].'%'" />
    </div>

    <div class="mt-6">
        <x-card title="Per-question performance">
            @if ($statistics['questionStats']->isEmpty())
                <x-empty-state title="No questions yet" description="Add questions to this assessment to see per-question statistics." />
            @else
                <x-table caption="Per-question performance">
                    <x-slot:head>
                        <th class="px-3 py-2">Question</th>
                        <th class="px-3 py-2">Answered</th>
                        <th class="px-3 py-2">Correct</th>
                        <th class="px-3 py-2">Correct rate</th>
                    </x-slot:head>

                    @foreach ($statistics['questionStats'] as $stat)
                        <tr>
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ Str::limit($stat['body'], 60) }}</td>
                            <td class="px-3 py-2 text-neutral-500">{{ $stat['answeredCount'] }}</td>
                            <td class="px-3 py-2 text-neutral-500">{{ $stat['correctCount'] }}</td>
                            <td class="px-3 py-2">{{ $stat['correctRate'] }}%</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </div>

    <div class="mt-6">
        <x-card title="Attempts">
            @if ($attempts->isEmpty())
                <x-empty-state title="No attempts yet" description="Student attempts on this assessment appear here." />
            @else
                <x-table caption="Attempts">
                    <x-slot:head>
                        <th class="px-3 py-2">Student</th>
                        <th class="px-3 py-2">Attempt</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Score</th>
                        <th class="px-3 py-2">Started</th>
                    </x-slot:head>

                    @foreach ($attempts as $attempt)
                        <tr wire:key="attempt-{{ $attempt->id }}">
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ $attempt->user?->name }}</td>
                            <td class="px-3 py-2 text-neutral-500">#{{ $attempt->attempt_number }}</td>
                            <td class="px-3 py-2">
                                <x-badge variant="neutral">{{ ucfirst($attempt->status->value) }}</x-badge>
                            </td>
                            <td class="px-3 py-2">
                                @if ($attempt->status->value === 'graded')
                                    <x-badge :variant="$attempt->is_passed ? 'success' : 'danger'">{{ $attempt->score_percentage }}%</x-badge>
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-3 py-2 text-neutral-500">{{ $attempt->started_at?->format('d M Y, H:i') }}</td>
                        </tr>
                    @endforeach
                </x-table>

                <div class="mt-4">
                    {{ $attempts->links() }}
                </div>
            @endif
        </x-card>
    </div>
</div>
