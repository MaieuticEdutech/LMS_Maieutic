@props([
    // list<array{value: string, label: string}>
    'items' => [],
])

{{-- The row of headline figures under a hero. Every value is passed in by the
     caller from real data; this component never invents a number. --}}
<dl {{ $attributes->class('grid grid-cols-2 overflow-hidden rounded-[20px] border border-neutral-200 bg-white shadow-md md:grid-cols-4') }}>
    @foreach ($items as $item)
        <div class="flex flex-col-reverse gap-1 px-6 py-5 {{ $loop->first ? '' : 'md:border-l' }} {{ $loop->index >= 2 ? 'border-t md:border-t-0' : '' }} {{ $loop->index % 2 === 1 ? 'border-l' : '' }} border-neutral-200">
            <dt class="text-[12.5px] text-neutral-500">{{ $item['label'] }}</dt>
            <dd class="font-serif text-[30px] leading-none text-neutral-900">{{ $item['value'] }}</dd>
        </div>
    @endforeach
</dl>
