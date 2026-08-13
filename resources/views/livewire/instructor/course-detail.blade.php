<div>
    <div class="mb-6 flex items-center gap-3">
        <h1 class="flex-1 text-2xl">{{ $course->title }}</h1>
        <x-badge :variant="$course->status->badgeVariant()">{{ $course->status->label() }}</x-badge>
    </div>

    <div class="mb-6 rounded-card border border-neutral-200 bg-white p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="eyebrow text-neutral-500">Average progress</p>
            <p class="font-serif text-2xl font-semibold tracking-[-0.015em] text-neutral-900">{{ $averageProgress }}%</p>
        </div>
        <x-progress-bar :value="$averageProgress" class="mt-3" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card title="Students" :description="$students->count().' enrolled'">
            @if ($students->isEmpty())
                <x-empty-state title="No students yet" description="Enrolled students appear here." />
            @else
                <x-table caption="Enrolled students">
                    <x-slot:head>
                        <th class="px-3 py-2">Student</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Progress</th>
                        <th class="px-3 py-2">Enrolled</th>
                        <th class="px-3 py-2"></th>
                    </x-slot:head>

                    @foreach ($students as $enrollment)
                        <tr wire:key="student-{{ $enrollment->id }}">
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ $enrollment->user?->name }}</td>
                            <td class="px-3 py-2"><x-badge :variant="$enrollment->status->badgeVariant()">{{ $enrollment->status->label() }}</x-badge></td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <x-progress-bar :value="$enrollment->progress_percentage" size="sm" class="w-16" />
                                    <span class="font-mono text-xs text-neutral-500">{{ $enrollment->progress_percentage }}%</span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-neutral-500">{{ $enrollment->enrolled_at?->format('d M Y') }}</td>
                            <td class="px-3 py-2 text-right">
                                <x-button :href="route('instructor.courses.students.progress', [$course, $enrollment])" variant="secondary" size="sm" wire:navigate>View progress</x-button>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        <x-card title="Assessments" :description="$assessments->count().' in this course'">
            @if ($assessments->isEmpty())
                <x-empty-state title="No assessments yet" description="Quizzes and tests attached to this course's lessons, modules or the course itself appear here." />
            @else
                <x-table caption="Assessments">
                    <x-slot:head>
                        <th class="px-3 py-2">Title</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2"></th>
                    </x-slot:head>

                    @foreach ($assessments as $assessment)
                        <tr wire:key="assessment-{{ $assessment->id }}">
                            <td class="px-3 py-2 font-medium text-neutral-900">{{ $assessment->title }}</td>
                            <td class="px-3 py-2">
                                <x-badge :variant="$assessment->is_published ? 'success' : 'neutral'">
                                    {{ $assessment->is_published ? 'Published' : 'Draft' }}
                                </x-badge>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <x-button :href="route('instructor.assessments.builder', $assessment)" variant="secondary" size="sm" wire:navigate>Open</x-button>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </div>
</div>
