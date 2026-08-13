@props([
    'title' => null,
    'description' => null,
])

{{--
    Card — white on paper, hairline border, 16px radius.

    Hierarchy in this system comes from the border and the spacing, NOT from
    shadow (docs/UI-GUIDE.md §8). A resting card carries no shadow at all;
    elevation is reserved for hover and for overlays. Coloured left-border
    accent cards are explicitly not part of the language.

    Headings are serif — the base layer handles that, so no font class here.
--}}

<div {{ $attributes->class('rounded-card border border-neutral-200 bg-white') }}>
    @if ($title || $description || isset($header))
        <div class="border-b border-neutral-200 px-5 py-4 sm:px-6">
            @isset($header)
                {{ $header }}
            @else
                @if ($title)
                    <h2 class="text-lg">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-1 text-sm text-neutral-500">{{ $description }}</p>
                @endif
            @endisset
        </div>
    @endif

    <div class="px-5 py-5 sm:px-6">
        {{ $slot }}
    </div>

    @isset($footer)
        {{-- Sunken footer: sets the action row apart from the content without
             needing a second border weight. --}}
        <div class="rounded-b-card border-t border-neutral-200 bg-neutral-50 px-5 py-4 sm:px-6">
            {{ $footer }}
        </div>
    @endisset
</div>
