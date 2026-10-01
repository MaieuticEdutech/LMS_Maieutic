{{--
    Upload Documents — a profile photo and an Aadhaar card scan.

    Deliberately its own card, its own component, its own two independent
    upload actions: see ProfileDocumentsForm's class docblock for why this
    does not live inside "Basic Details".

    Each block commits the moment a file is chosen (wire:model plus
    ProfileDocumentsForm's updated{Property}() hooks) — there is no "Save"
    button here, because there is nothing left unsaved once a file has
    finished uploading.

    Each field uses x-file-drop-zone (resources/views/components/) — a plain
    file input, but a click OR a drag-and-drop lands on the same input, so
    both reach wire:model the identical, already-tested way. That component
    is new and general-purpose; nothing here depends on it in a way specific
    to documents.
--}}
{{-- Header slot rather than title=: the profile page's section headings all
     share a serif/teal-600 treatment (see profile-form.blade.php), and
     x-card's own title rendering has no colour hook. --}}
<x-card>
    <x-slot:header>
        <h2 class="text-lg text-teal-600">Upload Documents</h2>
        <p class="mt-1 text-sm text-neutral-500">A photo and an Aadhaar card scan.</p>
    </x-slot:header>
    <div class="space-y-6">

        {{-- ══ PHOTO ══ --}}
        <div>
            <p class="text-sm font-medium text-neutral-900">
                Passport size/style photo
            </p>
            <p class="mt-1 text-xs text-neutral-500">Up to 5MB. JPEG, JPG or PNG.</p>

            @if ($avatarOriginalName)
                <div class="mt-3 flex items-center gap-3 rounded-control border border-neutral-200 bg-neutral-50 p-3">
                    {{-- The photo itself, not a generic file icon: the whole
                         point of a passport-style photo is to look at it. --}}
                    <img
                        src="{{ route('profile.photo') }}"
                        alt="Your uploaded photo"
                        class="size-16 shrink-0 rounded-control border border-neutral-200 object-cover"
                    >

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm text-neutral-800">{{ $avatarOriginalName }}</p>
                        @if ($avatarSizeBytes)
                            <p class="text-xs text-neutral-500">{{ \Illuminate\Support\Number::fileSize($avatarSizeBytes) }}</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <a href="{{ route('profile.photo') }}" target="_blank" rel="noopener"
                           class="text-xs font-medium text-teal-600 hover:underline">
                            Preview
                        </a>
                        <button type="button" wire:click="removePhoto" wire:confirm="Remove your uploaded photo?"
                                class="text-xs font-medium text-red-600 hover:underline">
                            Remove
                        </button>
                    </div>
                </div>
            @endif

            {{-- Always present, even once a photo exists — choosing a new file
                 here replaces the current one (UploadAvatar deletes the old
                 file only after the new one is safely stored). --}}
            <div class="mt-3">
                <x-file-drop-zone
                    name="photo"
                    accept="image/jpeg,image/png"
                    :label="$avatarOriginalName ? 'Replace your photo' : 'Choose a photo'"
                />
                <span wire:loading wire:target="photo" class="mt-1.5 block text-xs text-neutral-500">Uploading…</span>
                @error('photo')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- ══ AADHAAR CARD ══ --}}
        <div class="border-t border-neutral-200 pt-6">
            <p class="text-sm font-medium text-neutral-900">Aadhaar card</p>
            <p class="mt-1 text-xs text-neutral-500">Up to 10MB. JPEG, JPG, PNG or PDF.</p>

            @if ($aadhaarDocumentOriginalName)
                <div class="mt-3 flex items-center gap-3 rounded-control border border-neutral-200 bg-neutral-50 p-3">
                    {{-- No thumbnail: the file may be a PDF, and a document
                         this sensitive is not something to render inline
                         where it would sit in the page unasked for. --}}
                    <div class="flex size-16 shrink-0 items-center justify-center rounded-control border border-neutral-200 bg-white text-neutral-400" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
                            <path d="M14 2v6h6"></path>
                        </svg>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm text-neutral-800">{{ $aadhaarDocumentOriginalName }}</p>
                        @if ($aadhaarDocumentSizeBytes)
                            <p class="text-xs text-neutral-500">{{ \Illuminate\Support\Number::fileSize($aadhaarDocumentSizeBytes) }}</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <a href="{{ route('profile.aadhaar-document') }}" target="_blank" rel="noopener"
                           class="text-xs font-medium text-teal-600 hover:underline">
                            Preview
                        </a>
                        <button type="button" wire:click="removeAadhaarDocument" wire:confirm="Remove your uploaded Aadhaar card?"
                                class="text-xs font-medium text-red-600 hover:underline">
                            Remove
                        </button>
                    </div>
                </div>
            @endif

            <div class="mt-3">
                <x-file-drop-zone
                    name="aadhaarDocument"
                    accept="image/jpeg,image/png,application/pdf"
                    :label="$aadhaarDocumentOriginalName ? 'Replace your Aadhaar card' : 'Choose your Aadhaar card'"
                />
                <span wire:loading wire:target="aadhaarDocument" class="mt-1.5 block text-xs text-neutral-500">Uploading…</span>
                @error('aadhaarDocument')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>
</x-card>
