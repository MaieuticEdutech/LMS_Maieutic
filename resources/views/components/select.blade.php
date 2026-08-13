@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'required' => false,
    'placeholder' => null,
])

@php
    $id = $attributes->get('id') ?? $name;
    $hasError = $name && $errors->has($name);
    $describedBy = collect([
        $hint ? "{$id}-hint" : null,
        $hasError ? "{$id}-error" : null,
    ])->filter()->implode(' ');
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-neutral-900">
            {{ $label }}
            @if ($required)
                <span class="text-red-600" aria-hidden="true">*</span>
                <span class="sr-only">(required)</span>
            @endif
        </label>
    @endif

    <select
        id="{{ $id }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class([
            'block h-10 w-full rounded-control border bg-white px-3 text-sm text-neutral-900',
            'transition-colors duration-150 ease-out',
            'disabled:cursor-not-allowed disabled:bg-neutral-100 disabled:text-neutral-500',
            $label ? 'mt-1.5' : '',
            $hasError ? 'border-red-600' : 'border-neutral-200 hover:border-neutral-300',
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>

    @if ($hint && ! $hasError)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-neutral-500">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p id="{{ $id }}-error" class="mt-1.5 text-xs text-red-600">{{ $errors->first($name) }}</p>
    @endif
</div>
