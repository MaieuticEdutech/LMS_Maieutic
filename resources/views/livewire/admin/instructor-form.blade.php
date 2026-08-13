<div class="max-w-xl">
    <x-card :title="$instructor ? 'Edit instructor' : 'Add instructor'">
        <form wire:submit="save" class="space-y-4">
            <x-input wire:model="name" label="Full name" name="name" required />
            <x-input wire:model="email" type="email" label="Email address" name="email" required />
            <x-input wire:model="phone" type="tel" label="Phone (optional)" name="phone" />
            <x-input wire:model="headline" label="Headline (optional)" name="headline" hint="Shown on the course detail page, e.g. &quot;Senior Backend Engineer&quot;." />
            <x-textarea wire:model="bio" label="Bio (optional)" name="bio" rows="4" />

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
