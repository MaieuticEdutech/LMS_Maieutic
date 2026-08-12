@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'rows' => 4,
    'required' => false,
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
        <label for="{{ $id }}" class="block text-sm font-medium text-zinc-900">
            {{ $label }}
            @if ($required)
                <span class="text-danger-600" aria-hidden="true">*</span>
                <span class="sr-only">(required)</span>
            @endif
        </label>
    @endif

    <textarea
        id="{{ $id }}"
        rows="{{ $rows }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class([
            'block w-full rounded-control border-0 py-1.5 text-sm text-zinc-900 shadow-sm ring-1 ring-inset placeholder:text-zinc-400',
            'focus:ring-2 focus:ring-inset focus:ring-brand-600',
            $label ? 'mt-1.5' : '',
            $hasError ? 'ring-danger-600' : 'ring-zinc-300',
        ]) }}>{{ $slot }}</textarea>

    @if ($hint && ! $hasError)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-zinc-500">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p id="{{ $id }}-error" class="mt-1.5 text-xs text-danger-600">{{ $errors->first($name) }}</p>
    @endif
</div>
