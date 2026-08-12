@props([
    'title' => null,
    'description' => null,
])

<div {{ $attributes->class('rounded-card bg-white shadow-sm ring-1 ring-zinc-200') }}>
    @if ($title || $description || isset($header))
        <div class="border-b border-zinc-200 px-4 py-3 sm:px-6">
            @isset($header)
                {{ $header }}
            @else
                @if ($title)
                    <h2 class="text-sm font-semibold text-zinc-900">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-zinc-500">{{ $description }}</p>
                @endif
            @endisset
        </div>
    @endif

    <div class="px-4 py-4 sm:px-6">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-zinc-200 px-4 py-3 sm:px-6">
            {{ $footer }}
        </div>
    @endisset
</div>
