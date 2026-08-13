@props(['label' => '', 'value' => ''])

{{--
    A single KPI figure. Used by the admin dashboard's tile row and any
    future summary screen — extend with a trend/icon slot if one is ever
    needed rather than forking a second tile component.

    The label is a mono eyebrow and the figure is serif. That pairing is the
    brand's type signature (docs/UI-GUIDE.md §6), and a tile row is where it
    reads most clearly — a wall of sans numbers is what makes a dashboard look
    like every other dashboard.
--}}
<div {{ $attributes->class('rounded-card border border-neutral-200 bg-white p-5') }}>
    <p class="eyebrow text-neutral-500">{{ $label }}</p>
    <p class="mt-2 font-serif text-3xl font-semibold tracking-[-0.015em] text-neutral-900">{{ $value }}</p>
</div>
