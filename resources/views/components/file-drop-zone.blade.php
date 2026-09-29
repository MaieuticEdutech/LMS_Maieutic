@props([
    'name' => null,
    'accept' => null,
    'label' => 'Choose a file',
])

@php
    $id = $attributes->get('id') ?? $name;
@endphp

{{--
    A file input sized to cover a visible drop target.

    DRAGGING A FILE ONTO THE TARGET NEEDS NO SPECIAL HANDLING TO REACH
    wire:model. The <input> itself is stretched (`absolute inset-0`) to cover
    the whole visible box, opacity-0 so only the decorative icon and text
    beneath it show through. Dropping a file anywhere in the box therefore
    drops it ON THE INPUT — ordinary browser behaviour then populates
    input.files and fires change exactly as a click-to-browse selection would,
    which is the same event Livewire's wire:model already listens for. Alpine
    here only toggles the highlight on drag-over; it never touches the file
    itself, so there is nothing here that could feed wire:model a file
    Livewire did not itself receive via the native input.
--}}
<div
    x-data="{ dragging: false }"
    x-on:dragenter.prevent="dragging = true"
    x-on:dragover.prevent="dragging = true"
    x-on:dragleave.prevent="dragging = false"
    x-on:drop.prevent="dragging = false"
    {{ $attributes->class([
        'relative flex flex-col items-center justify-center gap-1.5 overflow-hidden rounded-control border-2 border-dashed px-4 py-6 text-center transition-colors',
    ]) }}
    x-bind:class="dragging ? 'border-teal-500 bg-teal-50' : 'border-neutral-300 bg-neutral-50 hover:border-neutral-400'"
>
    <input
        type="file"
        id="{{ $id }}"
        @if ($name) wire:model="{{ $name }}" @endif
        @if ($accept) accept="{{ $accept }}" @endif
        aria-label="{{ $label }}"
        class="absolute inset-0 size-full cursor-pointer opacity-0"
    >

    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="pointer-events-none text-neutral-400">
        <circle cx="12" cy="12" r="10"></circle>
        <path d="M12 8v8"></path>
        <path d="m8 12 4 4 4-4"></path>
    </svg>

    <p class="pointer-events-none text-sm text-neutral-600">
        <span class="font-medium text-teal-600">Click to browse</span>, or drag and drop a file here
    </p>

    {{ $slot ?? '' }}
</div>
