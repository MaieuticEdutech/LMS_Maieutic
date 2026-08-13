@props([
    'title' => 'Nothing here yet',
    'description' => null,
])

{{--
    Empty state.

    NFR-UX-06 / Phase 15 DoD: every data surface has a loading, empty and
    error state. An empty table with no explanation reads as a bug; this
    component makes "there is genuinely nothing here" explicit and offers the
    next action where one exists.

    This is the most-seen component in the product on day one — the entire
    system is empty until an admin fills it — so it is worth more care than
    its size suggests. Say what would be here, then give the action that
    creates it. Never a bare "No results".
--}}

<div {{ $attributes->class('rounded-card border border-dashed border-neutral-300 bg-neutral-50/60 px-6 py-12 text-center') }}>
    <h3 class="text-lg">{{ $title }}</h3>

    @if ($description)
        {{-- Capped measure: a centred paragraph running the full width of a
             table is unreadable. --}}
        <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-neutral-500">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-5 flex justify-center">
            {{ $action }}
        </div>
    @endisset
</div>
