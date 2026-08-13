<div>
    @forelse ($media as $file)
        <div wire:key="media-{{ $file->id }}" class="mb-2 flex items-center gap-3 rounded-control border border-neutral-200 bg-neutral-50 px-3.5 py-2.5">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-teal-600" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"></path><path d="M14 2v5h5"></path></svg>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold text-neutral-900">{{ $file->original_name }}</div>
                <div class="text-xs text-neutral-500">{{ $file->humanSize() }}</div>
            </div>
            <label class="cursor-pointer rounded-sm border border-neutral-300 bg-white px-2.5 py-1 text-xs font-semibold text-neutral-700 hover:bg-neutral-100">
                Replace
                <input type="file" wire:model="{{ $multiple ? 'files' : 'file' }}" class="sr-only">
            </label>
            <button type="button" wire:click="confirmRemove({{ $file->id }})" x-on:click="$dispatch('open-modal', 'remove-media-{{ $file->id }}')" class="flex h-7 w-7 items-center justify-center rounded-sm border border-neutral-200 text-neutral-500 hover:border-red-200 hover:text-red-600" aria-label="Remove file">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </button>

            <x-modal name="remove-media-{{ $file->id }}" title="Remove file">
                <p class="text-sm text-neutral-600">Remove <span class="font-medium">{{ $file->original_name }}</span>?</p>
                <x-slot:footer>
                    <x-button x-on:click="$dispatch('close-modal', 'remove-media-{{ $file->id }}')" variant="secondary" size="sm">Cancel</x-button>
                    <x-button wire:click="remove" x-on:click="$dispatch('close-modal', 'remove-media-{{ $file->id }}')" variant="danger" size="sm">Remove</x-button>
                </x-slot:footer>
            </x-modal>
        </div>
    @empty
        <label class="block cursor-pointer rounded-control border-2 border-dashed border-neutral-300 px-5 py-6 text-center hover:border-teal-400 hover:bg-teal-50">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1.5 text-neutral-400" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
            <span class="block text-sm font-semibold text-neutral-900">Drop a file or browse</span>
            <span class="block text-xs text-neutral-500">Up to the configured size limit for this content type</span>
            <input type="file" wire:model="{{ $multiple ? 'files' : 'file' }}" @if ($multiple) multiple @endif class="sr-only">
        </label>
    @endforelse

    <div wire:loading wire:target="{{ $multiple ? 'files' : 'file' }}" class="mt-2 rounded-control border border-neutral-200 bg-neutral-50 px-3.5 py-2.5">
        <div class="mb-1.5 h-1.5 overflow-hidden rounded-full bg-neutral-200">
            <div class="h-full animate-pulse rounded-full bg-teal-600" style="width: 60%"></div>
        </div>
        <p class="text-xs text-neutral-500">Uploading&hellip;</p>
    </div>

    @error('file')
        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
