@props(['items' => []])

{{--
    Breadcrumb trail for the admin area.

    Presentation only — has no bearing on authorisation (Development Rule 20).
    `items` is a list of ['label' => string, 'url' => string|null]; the last
    entry renders as plain text (the current page) regardless of whether a
    url is given.
--}}
@if (count($items) > 0)
    <nav aria-label="Breadcrumb" {{ $attributes->class('mb-2') }}>
        <ol class="flex flex-wrap items-center gap-1 text-sm text-neutral-500">
            @foreach ($items as $item)
                <li class="flex items-center gap-1">
                    @if (! $loop->first)
                        <span aria-hidden="true" class="text-neutral-300">/</span>
                    @endif

                    @if (! empty($item['url']) && ! $loop->last)
                        <a href="{{ $item['url'] }}" class="hover:text-neutral-700 hover:underline">
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span class="font-medium text-neutral-700" @if ($loop->last) aria-current="page" @endif>
                            {{ $item['label'] }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
