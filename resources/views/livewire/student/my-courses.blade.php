{{--
    My Courses (FR-STU-06).

    Every course this student can open. Courses whose access has ended are
    absent rather than greyed out — see the component docblock for why.
--}}
<div class="space-y-8">
    <div>
        <p class="eyebrow text-teal-600">Your library</p>
        <h1 class="mt-2 text-[2rem]">My courses</h1>
    </div>

    @if ($enrollments->isEmpty())
        <x-empty-state
            title="Nothing here yet"
            description="Courses you are enrolled in will appear here, with your progress against each one."
        />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($enrollments as $enrollment)
                @include('livewire.student.partials.course-card', ['enrollment' => $enrollment])
            @endforeach
        </div>
    @endif
</div>
