@props(['label' => '', 'value' => ''])

{{--
    A single KPI figure. Used by the admin dashboard's tile row and any
    future summary screen — extend with a trend/icon slot if one is ever
    needed rather than forking a second tile component.
--}}
<div {{ $attributes->class('rounded-card bg-white p-4 ring-1 ring-zinc-200') }}>
    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold text-zinc-900">{{ $value }}</p>
</div>
