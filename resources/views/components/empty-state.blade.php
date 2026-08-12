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
--}}

<div {{ $attributes->class('rounded-card border border-dashed border-zinc-300 px-6 py-10 text-center') }}>
    <h3 class="text-sm font-semibold text-zinc-900">{{ $title }}</h3>

    @if ($description)
        <p class="mx-auto mt-1 max-w-sm text-sm text-zinc-500">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-4 flex justify-center">
            {{ $action }}
        </div>
    @endisset
</div>
