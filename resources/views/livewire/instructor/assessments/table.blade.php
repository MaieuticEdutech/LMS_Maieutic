<div>
    <h1 class="mb-6">Assessments</h1>

    @if ($assessments->isEmpty())
        <x-empty-state
            title="No assessments yet"
            description="Quizzes and tests on your assigned courses appear here. Add one from a quiz lesson's editor in the Course Builder, or from a course's final test section."
        />
    @else
        <x-table caption="Assessments">
            <x-slot:head>
                <th class="px-3 py-2">Title</th>
                <th class="px-3 py-2">Type</th>
                <th class="px-3 py-2">Questions</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2"></th>
            </x-slot:head>

            @foreach ($assessments as $assessment)
                <tr wire:key="assessment-{{ $assessment->id }}">
                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $assessment->title }}</td>
                    <td class="px-3 py-2 text-neutral-500">{{ ucfirst($assessment->type->value) }}</td>
                    <td class="px-3 py-2 text-neutral-500">{{ $assessment->questions_count }}</td>
                    <td class="px-3 py-2">
                        <x-badge :variant="$assessment->is_published ? 'success' : 'neutral'">
                            {{ $assessment->is_published ? 'Published' : 'Draft' }}
                        </x-badge>
                    </td>
                    <td class="px-3 py-2 text-right">
                        <div class="flex justify-end gap-2">
                            <x-button :href="route('instructor.assessments.builder', $assessment)" variant="secondary" size="sm" wire:navigate>Edit</x-button>
                            <x-button :href="route('instructor.assessments.results', $assessment)" variant="secondary" size="sm" wire:navigate>Results</x-button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</div>
