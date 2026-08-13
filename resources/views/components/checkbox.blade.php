@props([
    'label' => null,
    'name' => null,
    'hint' => null,
])

@php
    $id = $attributes->get('id') ?? $name;
    $hasError = $name && $errors->has($name);
@endphp

<div>
    <div class="flex items-start gap-2.5">
        <input
            type="checkbox"
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            @if ($hint) aria-describedby="{{ $id }}-hint" @endif
            {{ $attributes->class([
                'mt-0.5 size-4 shrink-0 rounded-xs border text-teal-600',
                'transition-colors duration-150 ease-out',
                $hasError ? 'border-red-600' : 'border-neutral-300',
            ]) }}
        >

        @if ($label)
            {{-- Cursor on the label too: the 16px box is a small target, and
                 the label is the rest of the hit area. --}}
            <label for="{{ $id }}" class="cursor-pointer text-sm text-neutral-800">{{ $label }}</label>
        @endif
    </div>

    @if ($hint)
        <p id="{{ $id }}-hint" class="ml-6.5 mt-1 text-xs text-neutral-500">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p class="ml-6.5 mt-1 text-xs text-red-600">{{ $errors->first($name) }}</p>
    @endif
</div>
