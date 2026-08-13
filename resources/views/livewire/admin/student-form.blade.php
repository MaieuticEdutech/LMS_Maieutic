<div class="max-w-xl">
    <x-card :title="$student ? 'Edit student' : 'Add student'">
        <form wire:submit="save" class="space-y-4">
            <x-input wire:model="name" label="Full name" name="name" required />
            <x-input wire:model="email" type="email" label="Email address" name="email" required />
            <x-input wire:model="phone" type="tel" label="Phone (optional)" name="phone" />

            @unless ($student)
                <p class="text-xs text-zinc-500">
                    No password is set here — the student receives an activation link by email to choose
                    their own, the same as a purchase-created account.
                </p>
            @endunless

            <div class="flex justify-end gap-2 pt-2">
                <x-button :href="route('admin.students.index')" variant="secondary" wire:navigate>Cancel</x-button>
                <x-button type="submit" variant="primary">{{ $student ? 'Save changes' : 'Create student' }}</x-button>
            </div>
        </form>
    </x-card>
</div>
