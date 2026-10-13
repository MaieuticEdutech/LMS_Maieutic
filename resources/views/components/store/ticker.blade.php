@props([
    // list<string>: real course or skill names.
    'items' => [],
])

{{-- A slow marquee of skills. Decorative (the same names are on the page as
     links), so hidden from assistive tech; paused on hover and stopped under
     reduced motion by the base layer. --}}
@if (count($items) > 0)
    <div {{ $attributes->class('overflow-hidden border-y border-neutral-200 bg-white py-4') }} aria-hidden="true">
        <div class="flex w-max animate-marquee gap-11 whitespace-nowrap font-serif text-[28px] italic text-neutral-900 hover:[animation-play-state:paused]">
            @foreach ([0, 1] as $copy)
                @foreach ($items as $item)
                    <span class="flex items-center gap-11">{{ $item }}<span class="text-lg not-italic text-melon">✦</span></span>
                @endforeach
            @endforeach
        </div>
    </div>
@endif
