@props([
    // CarbonImmutable: when the free launch ends (Promotion::endsAt()).
    'ends',
    'tone' => 'default',
])

{{--
    A live "ends in 12d 04h 31m" for the free-launch promotion. The end time is
    the real one from Promotion::endsAt(); without JavaScript the static date
    inside the element is what a visitor sees. Ticks once a second (Alpine ships
    with Livewire) and shows nothing once the moment has passed.
--}}
<span {{ $attributes->class(['inline-flex flex-wrap items-baseline gap-x-1.5 font-mono text-[12.5px] font-semibold', $tone === 'inverse' ? 'text-white' : 'text-teal-700']) }}
      wire:ignore
      x-data="{
          end: {{ $ends->getTimestamp() * 1000 }},
          left: null,
          tick() {
              const ms = this.end - Date.now();
              if (ms <= 0) { this.left = null; return; }
              const s = Math.floor(ms / 1000);
              const d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60);
              this.left = (d > 0 ? d + 'd ' : '') + String(h).padStart(2, '0') + 'h ' + String(m).padStart(2, '0') + 'm ' + String(s % 60).padStart(2, '0') + 's';
          },
      }"
      x-init="tick(); setInterval(() => tick(), 1000)">
    <span x-show="left === null">Free until {{ $ends->format('j M Y') }}</span>
    <span x-show="left !== null" x-cloak>Ends in <span x-text="left"></span></span>
</span>
