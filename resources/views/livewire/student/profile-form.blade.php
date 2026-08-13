{{--
    Editable profile details.

    Two separate forms with two separate submits, deliberately. A single
    "Save" covering both would mean typing your password to correct a
    misspelled name, and email changes would ride along unnoticed with a
    routine edit.
--}}
<div class="space-y-6">

    <x-card title="Your details" description="Visible to instructors on courses you are enrolled in.">
        <form wire:submit="saveDetails" class="space-y-5">
            <x-input label="Name" name="name" wire:model="name" required />

            <x-input
                label="Phone"
                name="phone"
                wire:model="phone"
                hint="Optional. Used only if an administrator needs to contact you."
            />

            <div class="flex items-center gap-3">
                <x-button type="submit" wire:loading.attr="disabled">Save details</x-button>
                <span wire:loading wire:target="saveDetails" class="text-sm text-neutral-500">Saving…</span>
            </div>
        </form>
    </x-card>

    <x-card
        title="Email address"
        description="Changing this signs you out until you confirm the new address."
    >
        <form wire:submit="saveEmail" class="space-y-5">
            <x-input label="Email address" name="email" type="email" wire:model="email" required />

            {{-- Required here and nowhere else on this page. A changed email
                 moves every password-reset link to a new address, so it is the
                 one edit worth proving you are present for. --}}
            <x-input
                label="Current password"
                name="currentPassword"
                type="password"
                wire:model="currentPassword"
                autocomplete="current-password"
                hint="Required to change your email address."
                required
            />

            <x-alert variant="warning">
                We will send a verification link to the new address. Until you open it,
                you will not be able to sign in — so check the address carefully.
            </x-alert>

            <div class="flex items-center gap-3">
                <x-button type="submit" wire:loading.attr="disabled">Change email</x-button>
                <span wire:loading wire:target="saveEmail" class="text-sm text-neutral-500">Sending…</span>
            </div>
        </form>
    </x-card>
</div>
