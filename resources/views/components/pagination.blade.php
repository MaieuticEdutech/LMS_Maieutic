@props([
    'paginator',
])

{{--
    Pagination wrapper.

    NFR-PERF-02: pagination is mandatory on every list in this application.
    This component exists so no view hand-rolls paging markup and quietly
    drops the links.

    Laravel's Tailwind pagination view is used underneath; wrapping it keeps a
    single place to restyle paging across all four audiences.
--}}

@if ($paginator->hasPages())
    <nav {{ $attributes->merge(['aria-label' => 'Pagination']) }}>
        {{ $paginator->onEachSide(1)->links() }}
    </nav>
@endif
