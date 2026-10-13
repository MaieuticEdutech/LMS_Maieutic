@props([
    'course',
    'rank',
    'href' => null,
])

{{-- A Top 10 entry: a big hollow numeral with the course poster tucked over it. --}}
<a href="{{ $href }}" {{ $attributes->class('group flex items-end') }}>
    <span class="numeral-outline -mr-6 font-serif text-[120px] leading-[0.8] tracking-[-0.06em] transition-colors md:text-[150px] [-webkit-text-stroke-color:var(--color-neutral-300)] group-hover:[-webkit-text-stroke-color:var(--color-teal-600)]" aria-hidden="true">{{ $rank }}</span>
    <span class="relative w-[62%] overflow-hidden rounded-[14px] shadow-lg">
        <x-store.course-art :course="$course" ratio="3/4" :label="false" />
        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-teal-900/95 to-transparent px-2.5 pb-2.5 pt-7 text-xs font-bold leading-tight text-white">
            <span class="sr-only">Number {{ $rank }}: </span>{{ $course->title }}
        </span>
    </span>
</a>
