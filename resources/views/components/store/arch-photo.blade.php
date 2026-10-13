@props([
    'src',
    'position' => 'center',
])

{{--
    The Canvas hero photo: an arch-framed image with floating glass cards in the
    default slot (position them absolutely). Decorative, so the photo has no alt
    text; the cards carry the content.
--}}
<div {{ $attributes->class('relative') }}>
    <div class="pointer-events-none absolute left-[4%] top-[12%] aspect-square w-[38%] rounded-full bg-melon/35 blur-[2px]" aria-hidden="true"></div>
    <div class="rounded-arch absolute inset-y-0 right-0 w-[86%] bg-cover shadow-float"
         style="background-image: url('{{ $src }}'); background-position: {{ $position }}"
         aria-hidden="true"></div>
    {{ $slot }}
</div>
