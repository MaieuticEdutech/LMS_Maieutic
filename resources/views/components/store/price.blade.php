@props([
    'course',
    // True while the free launch promotion is running. The caller decides,
    // so this component never reaches for settings on its own.
    'free' => false,
    'size' => 'md',
    'tone' => 'default',
])

{{--
    A course's price. During the free launch the real price is shown struck
    through beside "Free", so the offer reads as an offer. The real price
    stays the course's actual price (courses.price_amount, CHECK > 0); nothing
    here changes what an order would charge.
--}}
@php
    $money = $course->price;
    $whole = fmod($money->toMajor(), 1.0) === 0.0;
    $label = '₹'.number_format($money->toMajor(), $whole ? 0 : 2);
    $sizes = ['sm' => 'text-[15px]', 'md' => 'text-lg', 'lg' => 'text-[30px]'];
    $strong = $tone === 'inverse' ? 'text-white' : 'text-neutral-900';
    $muted = $tone === 'inverse' ? 'text-white/55' : 'text-neutral-500';
@endphp

<span {{ $attributes->class('inline-flex flex-wrap items-baseline gap-x-2 gap-y-1') }}>
    @if ($free)
        <b class="{{ $sizes[$size] ?? $sizes['md'] }} font-extrabold {{ $strong }}">Free</b>
        <s class="text-[13px] {{ $muted }}"><span class="sr-only">Usually </span>{{ $label }}</s>
        <span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-bold text-red-600">For a limited time</span>
    @else
        <b class="{{ $sizes[$size] ?? $sizes['md'] }} font-extrabold {{ $strong }}">{{ $label }}</b>
    @endif
</span>
