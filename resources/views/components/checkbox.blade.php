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
    <div class="flex items-start gap-2">
        <input
            type="checkbox"
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            @if ($hint) aria-describedby="{{ $id }}-hint" @endif
            {{ $attributes->class('mt-0.5 size-4 rounded border-zinc-300 text-brand-600 focus:ring-brand-600') }}
        >

        @if ($label)
            <label for="{{ $id }}" class="text-sm text-zinc-900">{{ $label }}</label>
        @endif
    </div>

    @if ($hint)
        <p id="{{ $id }}-hint" class="ml-6 mt-1 text-xs text-zinc-500">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p class="ml-6 mt-1 text-xs text-danger-600">{{ $errors->first($name) }}</p>
    @endif
</div>
