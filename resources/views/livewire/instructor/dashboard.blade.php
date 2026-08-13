<div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-tile label="Assigned courses" :value="$assignedCourseCount" />
        <x-stat-tile label="Students" :value="$studentCount" />
        <x-stat-tile label="Assessments" :value="$assessmentCount" />
    </div>

    <div class="mt-6">
        <x-card title="Recent attempts">
            @if ($recentAttempts->isEmpty())
                <x-empty-state title="No attempts yet" description="Submitted attempts on your courses' assessments appear here." />
            @else
                <x-table>
                    <x-slot:head>
                        <th class="px-3 py-2">Student</th>
                        <th class="px-3 py-2">Assessment</th>
                        <th class="px-3 py-2">Score</th>
                        <th class="px-3 py-2">Submitted</th>
                    </x-slot:head>

                    @foreach ($recentAttempts as $attempt)
                        <tr>
                            <td class="px-3 py-2">{{ $attempt->user?->name }}</td>
                            <td class="px-3 py-2 text-neutral-500">{{ $attempt->assessment?->title }}</td>
                            <td class="px-3 py-2">
                                @if ($attempt->status->value === 'graded')
                                    <x-badge :variant="$attempt->is_passed ? 'success' : 'danger'">{{ $attempt->score_percentage }}%</x-badge>
                                @else
                                    <x-badge variant="neutral">{{ ucfirst($attempt->status->value) }}</x-badge>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-neutral-500">{{ $attempt->submitted_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </div>
</div>
