@props([
    'name',
    'title' => null,
    'maxWidth' => 'md',
])

{{--
    Accessible dialog, driven by Alpine (bundled with Livewire).

    Opened with:  $dispatch('open-modal', 'delete-course')
    Closed with:  $dispatch('close-modal', 'delete-course') or Escape.

    Accessibility (WCAG 2.1 AA):
      - role="dialog" + aria-modal + aria-labelledby
      - focus moves into the dialog on open and is trapped while it is open
      - Escape closes
      - background scroll is locked

    FR-ADM-17: destructive administrative actions require TYPED confirmation,
    not just a click. The confirming view supplies that input in the slot; this
    component only provides the dialog shell.
--}}

@php
    $widths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ];
@endphp

<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') { open = true; $nextTick(() => $refs.panel.focus()) }"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-labelledby="{{ $name }}-title" @endif
>
    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 bg-zinc-900/50"
        x-on:click="open = false"
        aria-hidden="true"
    ></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div
            x-ref="panel"
            tabindex="-1"
            x-show="open"
            x-transition
            x-trap.noscroll="open"
            {{ $attributes->class([
                'relative w-full rounded-card bg-white shadow-xl',
                $widths[$maxWidth] ?? $widths['md'],
            ]) }}
        >
            @if ($title)
                <div class="border-b border-zinc-200 px-4 py-3 sm:px-6">
                    <h2 id="{{ $name }}-title" class="text-sm font-semibold text-zinc-900">{{ $title }}</h2>
                </div>
            @endif

            <div class="px-4 py-4 sm:px-6">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex justify-end gap-2 border-t border-zinc-200 px-4 py-3 sm:px-6">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
