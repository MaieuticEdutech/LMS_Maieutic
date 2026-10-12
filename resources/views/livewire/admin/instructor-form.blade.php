<div class="max-w-xl">
    <x-card :title="$instructor ? 'Edit instructor' : 'Add instructor'">
        <form wire:submit="save" class="space-y-4">
            <x-input wire:model="name" label="Full name" name="name" required />
            <x-input wire:model="email" type="email" label="Email address" name="email" required />
            <x-input wire:model="phone" type="tel" label="Phone (optional)" name="phone" />
            <x-input wire:model="headline" label="Headline (optional)" name="headline" hint="Shown on the course detail page, e.g. &quot;Senior Backend Engineer&quot;." />
            <x-textarea wire:model="bio" label="Bio (optional)" name="bio" rows="4" />

            {{-- Public profile photo, shown on course cards and pages. --}}
            @php($currentPhoto = $instructor?->instructorProfile?->photoUrl())
            <div>
                <p class="text-sm font-medium text-neutral-900">Profile photo (optional)</p>
                <p class="mt-1 text-xs text-neutral-500">Shown publicly on course pages. JPG, PNG or WebP, up to 2 MB. Square photos work best.</p>

                <div class="mt-3 flex items-center gap-4">
                    @if ($photo && $photo->isPreviewable())
                        <img src="{{ $photo->temporaryUrl() }}" alt="New profile photo" class="size-16 rounded-full object-cover">
                    @elseif ($currentPhoto)
                        <img src="{{ $currentPhoto }}" alt="Current profile photo" class="size-16 rounded-full object-cover">
                    @endif

                    <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" id="photo"
                           class="text-sm text-neutral-700 file:mr-3 file:rounded-control file:border-0 file:bg-neutral-100 file:px-3 file:py-2 file:text-sm file:font-medium">

                    @if ($currentPhoto && ! $photo)
                        <button type="button" wire:click="removePhoto" wire:confirm="Remove this instructor's photo?"
                                class="text-xs font-medium text-red-600 hover:underline">Remove photo</button>
                    @endif
                </div>
                <span wire:loading wire:target="photo" class="mt-1.5 block text-xs text-neutral-500">Uploading…</span>
                @error('photo')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @unless ($instructor)
                <p class="text-xs text-neutral-500">
                    No password is set here — the instructor receives an activation link by email to choose
                    their own, the same as a purchase-created account.
                </p>
            @endunless

            <div class="flex justify-end gap-2 pt-2">
                <x-button :href="route('admin.instructors.index')" variant="secondary" wire:navigate>Cancel</x-button>
                <x-button type="submit" variant="primary">{{ $instructor ? 'Save changes' : 'Create instructor' }}</x-button>
            </div>
        </form>
    </x-card>
</div>
