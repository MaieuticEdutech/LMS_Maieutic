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
            {{-- Side by side on anything wider than a phone, stacked below —
                 two half-width boxes on a 360px screen would be cramped. --}}
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input label="First name" name="firstName" wire:model="firstName" autocomplete="given-name" required />
                <x-input label="Last name" name="lastName" wire:model="lastName" autocomplete="family-name" required />
            </div>

            {{-- Required now, and the hint says why rather than just marking it
                 with an asterisk. "We need this" without a reason reads as the
                 form being nosy. --}}
            <x-input
                label="Mobile number"
                name="phone"
                type="tel"
                wire:model="phone"
                autocomplete="tel"
                hint="So we can reach you about your enrolment or a course you are taking."
                required
            />

            {{-- Asked separately from the name above, because the two are
                 genuinely different: the name someone goes by day to day is
                 not always the one they want on a document shown to an
                 employer. Optional — blank means "use my name as above". --}}
            <x-input
                label="Name for your certificate"
                name="certificateName"
                wire:model="certificateName"
                autocomplete="off"
                hint="How your name should appear on any certificate you earn. Leave blank to use your name above."
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
