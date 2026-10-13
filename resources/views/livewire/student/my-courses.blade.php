{{--
    My Learning (FR-STU-06).

    The mockup's screen: a plain serif title, three underlined tabs, then the
    same 3-up card grid the dashboard uses.

    Courses whose access has ended are absent rather than greyed out — see the
    component docblock for why.
--}}
<div class="mx-auto w-full max-w-content px-5 pb-24 pt-10 lg:px-10">

    <div class="mb-8 rounded-hero bg-gradient-to-br from-marigold via-white to-teal-50 px-6 py-8 sm:px-10">
        <p class="eyebrow text-teal-600">Your library</p>
        <h1 class="mt-2 font-serif text-[40px]/[1.08] font-normal tracking-[-0.02em] text-neutral-900 md:text-[48px]">My Learning</h1>
    </div>

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
        <div class="mb-8 flex flex-wrap gap-2" role="tablist" aria-label="Filter your courses">
            @foreach ($this->tabs() as $value => $label)
                <button
                    type="button"
                    role="tab"
                    wire:click="$set('filter', '{{ $value }}')"
                    aria-selected="{{ $filter === $value ? 'true' : 'false' }}"
                    class="rounded-full px-4 py-2 text-[14px] font-semibold transition-colors {{ $filter === $value ? 'bg-teal-600 text-white' : 'bg-white text-neutral-600 ring-1 ring-inset ring-neutral-200 hover:text-neutral-900 hover:ring-neutral-300' }}"
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
