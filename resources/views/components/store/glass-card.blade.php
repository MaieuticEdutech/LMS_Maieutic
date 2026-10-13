@props([
    // Seconds of animation offset, so neighbouring cards don't bob in step.
    'delay' => 0,
    'float' => true,
])

{{-- A floating translucent card over a hero photo. Position it with classes
     from the caller (absolute + offsets). --}}
<div {{ $attributes->class(['glass rounded-[20px] px-[18px] py-4 text-neutral-900', 'animate-float' => $float]) }}
     @if ($delay) style="animation-delay: -{{ $delay }}s" @endif>
    {{ $slot }}
</div>
