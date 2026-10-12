@props([
    'title',
    'subtitle' => null,
    'href' => null,
    'linkLabel' => null,
    // Heading level. Sections under the page's h1 are h2.
    'as' => 'h2',
])

{{-- Title may contain one <em> for the brand's italic teal accent, so it is
     printed unescaped. Callers pass literals, never user input. --}}
<div {{ $attributes->class('mb-5 flex flex-wrap items-end justify-between gap-x-6 gap-y-2') }}>
    <div class="min-w-0">
        <{{ $as }} class="font-serif text-[30px] font-normal leading-[1.08] tracking-[-0.02em] text-neutral-900 [&_em]:italic [&_em]:text-teal-600 md:text-[38px]">{!! $title !!}</{{ $as }}>
        @if ($subtitle)
            <p class="mt-1.5 max-w-[56ch] text-[14.5px] text-neutral-500">{{ $subtitle }}</p>
        @endif
    </div>

    @if ($href && $linkLabel)
        <a href="{{ $href }}" class="inline-flex items-center gap-1.5 whitespace-nowrap text-[13.5px] font-bold text-teal-600 hover:text-teal-700">
            {{ $linkLabel }}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </a>
    @endif
</div>
