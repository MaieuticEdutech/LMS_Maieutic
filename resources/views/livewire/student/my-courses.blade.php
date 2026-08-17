{{--
    My Learning (FR-STU-06).

    The mockup's screen: a plain serif title, three underlined tabs, then the
    same 3-up card grid the dashboard uses.

    Courses whose access has ended are absent rather than greyed out — see the
    component docblock for why.
--}}
<div class="mx-auto w-full max-w-content px-5 pb-24 pt-12 lg:px-10">

    <h1 class="mb-8 font-serif text-[40px]/[1.1] font-medium">My Learning</h1>

    @if ($enrollments->isEmpty())
        <x-empty-state
            title="Nothing here yet"
            description="Courses you are enrolled in will appear here, with your progress against each one."
        />
    @else
        {{-- Tabs as buttons rather than links: this is a filter on a list
             already on screen, not navigation to a different page. The
             underline is the current-state signal, so it carries aria-current
             too — colour alone would not tell a screen-reader user which tab
             they are on. --}}
        <div class="mb-8 flex gap-2 border-b border-neutral-200" role="tablist" aria-label="Filter your courses">
            @foreach ($this->tabs() as $value => $label)
                <button
                    type="button"
                    role="tab"
                    wire:click="$set('filter', '{{ $value }}')"
                    aria-selected="{{ $filter === $value ? 'true' : 'false' }}"
                    class="-mb-px border-b-2 px-4 py-2.5 text-[14.5px] font-medium transition-colors {{ $filter === $value ? 'border-teal-600 text-teal-600' : 'border-transparent text-neutral-500 hover:text-neutral-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($visible->isEmpty())
            {{-- Distinct from the empty state above, deliberately. "You have no
                 courses" and "none of your courses are finished yet" are
                 different facts, and showing the first when the second is true
                 would read as the product having lost them. --}}
            <p class="py-12 text-center text-sm text-neutral-500">
                @if ($filter === 'completed')
                    You have not finished a course yet. Keep going.
                @else
                    Nothing in progress — every course you have is finished.
                @endif
            </p>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($visible as $enrollment)
                    @include('livewire.student.partials.course-card', ['enrollment' => $enrollment])
                @endforeach
            </div>
        @endif
    @endif
</div>
