<div class="max-w-2xl">
    <form wire:submit="save" class="space-y-6">
        @foreach ($groups as $group => $settings)
            <x-card :title="str($group)->headline()">
                <div class="space-y-4">
                    @foreach ($settings as $setting)
                        @php
                            $fieldName = "values.{$setting->key}";
                            $fieldLabel = str($setting->key)->afterLast('.')->headline();
                        @endphp

                        @if ($setting->type === 'boolean')
                            <x-checkbox wire:model="{{ $fieldName }}" :name="$fieldName" :label="$fieldLabel" />
                        @elseif ($setting->type === 'integer')
                            <x-input wire:model="{{ $fieldName }}" type="number" :name="$fieldName" :label="$fieldLabel" />
                        @else
                            <x-input wire:model="{{ $fieldName }}" type="text" :name="$fieldName" :label="$fieldLabel" />
                        @endif
                    @endforeach
                </div>
            </x-card>
        @endforeach

        <div class="flex justify-end">
            <x-button type="submit" variant="primary">Save changes</x-button>
        </div>
    </form>
</div>
