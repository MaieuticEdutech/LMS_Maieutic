@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'hint' => null,
    'required' => false,
])

{{--
    Text input with label, hint and inline error.

    NFR-UX-05 / WCAG 2.1 AA: every input is explicitly associated with its
    label via for/id, errors are rendered inline against the offending field,
    and aria-invalid + aria-describedby are set so screen readers announce the
    error rather than leaving the field silently wrong.

    A placeholder is never a label (docs/UI-GUIDE.md §12) — it disappears the
    moment the user types, taking the field's meaning with it.
--}}

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

    <input
        type="{{ $type }}"
        id="{{ $id }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class([
            'block h-10 w-full rounded-control border px-3 text-sm text-neutral-900',
            'bg-white placeholder:text-neutral-400',
            'transition-colors duration-150 ease-out',
            'disabled:cursor-not-allowed disabled:bg-neutral-100 disabled:text-neutral-500',
            $label ? 'mt-1.5' : '',
            // The error border is reinforced by the message below — colour
            // alone never carries meaning (WCAG 1.4.1).
            $hasError ? 'border-red-600' : 'border-neutral-200 hover:border-neutral-300',
        ]) }}
    >

    @if ($hint && ! $hasError)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-neutral-500">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p id="{{ $id }}-error" class="mt-1.5 text-xs text-red-600">{{ $errors->first($name) }}</p>
    @endif
</div>
