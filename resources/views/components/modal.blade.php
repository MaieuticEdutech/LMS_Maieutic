@props([
    'name',
    'title' => null,
    'maxWidth' => 'md',
    'placement' => 'center',
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

    placement="right": a full-height slide-over instead of a centred dialog
    (docs/UI-GUIDE.md's lesson editor). Geometry only changes — focus trap,
    Escape and scroll lock are identical either way.
--}}

@php
    $widths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ];

    $isRight = $placement === 'right';
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
    {{-- Ink scrim, not black: a neutral-black overlay over warm paper reads
         cold. Low opacity per the brand's "transparency used sparingly". --}}
    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 bg-neutral-900/40 backdrop-blur-[2px]"
        x-on:click="open = false"
        aria-hidden="true"
    ></div>

    <div @class(['flex min-h-full', $isRight ? 'items-stretch justify-end' : 'items-center justify-center p-4'])>
        <div
            x-ref="panel"
            tabindex="-1"
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="{{ $isRight ? 'translate-x-full' : 'opacity-0 scale-95' }}"
            x-transition:enter-end="{{ $isRight ? 'translate-x-0' : 'opacity-100 scale-100' }}"
            x-transition:leave="transition ease-standard duration-150"
            x-transition:leave-start="{{ $isRight ? 'translate-x-0' : 'opacity-100 scale-100' }}"
            x-transition:leave-end="{{ $isRight ? 'translate-x-full' : 'opacity-0 scale-95' }}"
            x-trap.noscroll="open"
            {{ $attributes->class(
                $isRight
                    ? ['relative flex h-full w-full flex-col border-l border-neutral-200 bg-white shadow-lg sm:w-drawer']
                    : [
                        'relative w-full rounded-card border border-neutral-200 bg-white shadow-lg',
                        $widths[$maxWidth] ?? $widths['md'],
                    ],
            ) }}
        >
            @if ($title)
                <div class="border-b border-neutral-200 px-5 py-4 sm:px-6">
                    <h2 id="{{ $name }}-title" class="text-lg">{{ $title }}</h2>
                </div>
            @endif

            <div @class(['px-5 py-5 text-sm text-neutral-800 sm:px-6', 'flex-1 overflow-y-auto' => $isRight])>
                {{ $slot }}
            </div>

            @isset($footer)
                <div @class([
                    'flex justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-5 py-4 sm:px-6',
                    'rounded-b-card' => ! $isRight,
                ])>
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
