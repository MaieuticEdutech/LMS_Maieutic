{{--
    Editable profile details.

    Two separate forms with two separate submits, deliberately. A single
    "Save" covering both would mean typing your password to correct a
    misspelled name, and email changes would ride along unnoticed with a
    routine edit.
--}}
<div class="space-y-6">

    {{-- Every section heading on the profile page — the four card titles and
         the three sub-sections below — shares one treatment: serif, 600,
         teal-600. That is the guide's "brand-forward heading" colour
         (docs/UI-GUIDE.md §5 colour table), used here so the page's sections
         read as one system rather than the mix of serif titles and bold sans
         labels they were. The card title goes through x-card's header slot
         only because the component's own title rendering carries no colour
         hook; the description is reproduced exactly as x-card renders it. --}}
    <x-card>
        <x-slot:header>
            <h2 class="text-lg text-teal-600">Basic Details</h2>
            <p class="mt-1 text-sm text-neutral-500">Visible to instructors on courses you are enrolled in.</p>
        </x-slot:header>
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

            {{-- Personal details — every field optional (NFR-DATA-01), and the
                 intro says so, so nobody feels a blank is an error. Set off by
                 a hairline rather than a second card: it saves with the same
                 button as everything above it. --}}
            {{-- Sub-section headings: same serif/teal treatment as the card
                 title, one size down (text-base) so the card still reads as
                 the container. An h3, not a bold <p> — it is a heading. --}}
            <div class="border-t border-neutral-200 pt-5">
                <h3 class="text-base text-teal-600">Personal Details</h3>
                <p class="mt-1 text-xs text-neutral-500">All optional. Fill in only what you are comfortable sharing.</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-select label="Title" name="personTitle" wire:model="personTitle" placeholder="Not specified">
                    @foreach ($titles as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </x-select>

                <x-select label="Gender" name="gender" wire:model="gender" placeholder="Not specified">
                    @foreach ($genders as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </x-select>

                {{-- .live so the age beside it updates as soon as a date is
                     picked, not only after saving. --}}
                <x-input
                    label="Date of birth"
                    name="dateOfBirth"
                    type="date"
                    wire:model.live="dateOfBirth"
                    autocomplete="bday"
                    max="{{ now()->subDay()->format('Y-m-d') }}"
                />

                {{-- Read-only and never stored: worked out from the date of
                     birth, so it cannot drift out of step with it. --}}
                <x-input
                    label="Age as on 1st April"
                    id="ageOnFirstApril"
                    :value="$ageOnFirstApril ?? '—'"
                    readonly
                    disabled
                    hint="Worked out from your date of birth."
                />

                <x-select label="Blood group" name="bloodGroup" wire:model="bloodGroup" placeholder="Not specified">
                    @foreach ($bloodGroups as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </x-select>

                <x-select label="Marital status" name="maritalStatus" wire:model="maritalStatus" placeholder="Not specified">
                    @foreach ($maritalStatuses as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </x-select>

                <x-input
                    label="Nationality"
                    name="nationality"
                    wire:model="nationality"
                    autocomplete="off"
                    placeholder="e.g. Indian"
                />
            </div>

            {{-- Identity numbers. The inputs are ALWAYS empty — the stored
                 number is never sent back to the browser, only its masked form
                 in the hint. Blank keeps it; a new number replaces it; Remove
                 clears it. See ProfileForm::$aadhaarNumber. --}}
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input
                        label="Aadhaar number"
                        name="aadhaarNumber"
                        wire:model="aadhaarNumber"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="14"
                        placeholder="{{ $maskedAadhaar ? 'Enter a new number to replace it' : 'XXXX XXXX XXXX' }}"
                        :hint="$maskedAadhaar
                            ? 'Saved as '.$maskedAadhaar.'. Leave blank to keep it.'
                            : '12 digits. Stored encrypted and never shown in full.'"
                    />
                    @if ($maskedAadhaar)
                        <button type="button" wire:click="removeAadhaar" wire:confirm="Remove your stored Aadhaar number?"
                                class="mt-1.5 text-xs font-medium text-red-600 hover:underline">
                            Remove Aadhaar number
                        </button>
                    @endif
                </div>

                <div>
                    <x-input
                        label="PAN"
                        name="panNumber"
                        wire:model="panNumber"
                        autocomplete="off"
                        maxlength="10"
                        class="uppercase"
                        placeholder="{{ $maskedPan ? 'Enter a new PAN to replace it' : 'ABCDE1234F' }}"
                        :hint="$maskedPan
                            ? 'Saved as '.$maskedPan.'. Leave blank to keep it.'
                            : '10 characters. Stored encrypted and never shown in full.'"
                    />
                    @if ($maskedPan)
                        <button type="button" wire:click="removePan" wire:confirm="Remove your stored PAN?"
                                class="mt-1.5 text-xs font-medium text-red-600 hover:underline">
                            Remove PAN
                        </button>
                    @endif
                </div>
            </div>

            {{-- Address — optional, same hairline treatment as Personal
                 details above. Country/state/district/city are plain text:
                 see the migration for why this is not a cascading places
                 picker. --}}
            <div class="border-t border-neutral-200 pt-5">
                <h3 class="text-base text-teal-600">Address</h3>
                <p class="mt-1 text-xs text-neutral-500">All optional. Fill in only what you are comfortable sharing.</p>
            </div>

            <div>
                <p class="text-sm font-medium text-neutral-900">Address for correspondence</p>

                <div class="mt-3 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-input label="Country" name="addressCountry" wire:model="addressCountry" autocomplete="country-name" />
                    <x-input label="State" name="addressState" wire:model="addressState" autocomplete="off" />
                    <x-input label="District" name="addressDistrict" wire:model="addressDistrict" autocomplete="off" />
                    <x-input label="City" name="addressCity" wire:model="addressCity" autocomplete="address-level2" />
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <x-input label="Address line 1" name="addressLine1" wire:model="addressLine1" autocomplete="address-line1" />
                    <x-input label="Address line 2" name="addressLine2" wire:model="addressLine2" autocomplete="address-line2" />
                    <x-input
                        label="Pincode"
                        name="addressPincode"
                        wire:model="addressPincode"
                        autocomplete="postal-code"
                        hint="If your country has no pincode or zip code, enter 0."
                    />
                </div>
            </div>

            {{-- Yes/No, not a checkbox: the question reads more naturally as
                 one, and it mirrors how the source design asked it. No custom
                 component exists for this — see resources/views/components/ —
                 so the two options are built inline, styled like x-checkbox's
                 own control. .live so the second address block appears the
                 moment "No" is chosen, without a save round trip. --}}
            <div>
                <p class="text-sm font-medium text-neutral-900">Is your permanent address the same as your address for correspondence?</p>
                <div class="mt-2.5 flex items-center gap-5">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-800">
                        <input type="radio" name="permanentSameAsCorrespondence" value="1" wire:model.live="permanentSameAsCorrespondence"
                               class="size-4 border-neutral-300 text-teal-600">
                        Yes
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-neutral-800">
                        <input type="radio" name="permanentSameAsCorrespondence" value="0" wire:model.live="permanentSameAsCorrespondence"
                               class="size-4 border-neutral-300 text-teal-600">
                        No
                    </label>
                </div>
            </div>

            @unless ($permanentSameAsCorrespondence)
                <div>
                    <p class="text-sm font-medium text-neutral-900">Permanent address</p>

                    <div class="mt-3 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <x-input label="Country" name="permanentCountry" wire:model="permanentCountry" autocomplete="off" required />
                        <x-input label="State" name="permanentState" wire:model="permanentState" autocomplete="off" />
                        <x-input label="District" name="permanentDistrict" wire:model="permanentDistrict" autocomplete="off" />
                        <x-input label="City" name="permanentCity" wire:model="permanentCity" autocomplete="off" />
                    </div>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <x-input label="Address line 1" name="permanentLine1" wire:model="permanentLine1" autocomplete="off" required />
                        <x-input label="Address line 2" name="permanentLine2" wire:model="permanentLine2" autocomplete="off" />
                        <x-input
                            label="Pincode"
                            name="permanentPincode"
                            wire:model="permanentPincode"
                            autocomplete="off"
                            hint="If your country has no pincode or zip code, enter 0."
                            required
                        />
                    </div>
                </div>
            @endunless

            {{-- Academic details — three fixed stages, all optional. Board and
                 degree name are free text (see the migration for why); marking
                 scheme and result status are the two genuinely closed lists.
                 "After Xth Qualification" is recorded but does not change
                 which fields appear below it — see ProfileForm's docblock. --}}
            <div class="border-t border-neutral-200 pt-5">
                <h3 class="text-base text-teal-600">Academic Details</h3>
                <p class="mt-1 text-xs text-neutral-500">All optional. Fill in only what you are comfortable sharing.</p>
            </div>

            <div>
                <p class="text-sm font-medium text-neutral-900">Class Xth</p>

                <div class="mt-3 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
                    <x-input label="School name" name="tenthSchoolName" wire:model="tenthSchoolName" autocomplete="off" />
                    <x-input label="Board" name="tenthBoard" wire:model="tenthBoard" autocomplete="off" />
                    <x-input label="Year of passing" name="tenthYearOfPassing" wire:model="tenthYearOfPassing" type="number" inputmode="numeric" />

                    <x-select label="Marking scheme" name="tenthMarkingScheme" wire:model="tenthMarkingScheme" placeholder="Not specified">
                        @foreach ($markingSchemes as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </x-select>

                    <x-input
                        label="Percentage/CGPA"
                        name="tenthScore"
                        wire:model="tenthScore"
                        type="number"
                        step="0.01"
                        inputmode="decimal"
                    />
                </div>
            </div>

            <x-select
                label="After Xth qualification"
                name="afterTenthQualification"
                wire:model="afterTenthQualification"
                placeholder="Not specified"
                hint="For our records only — it does not change the fields below."
            >
                @foreach ($afterTenthQualifications as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </x-select>

            <div>
                <p class="text-sm font-medium text-neutral-900">Class XIIth</p>

                <div class="mt-3 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <x-input label="School name" name="twelfthSchoolName" wire:model="twelfthSchoolName" autocomplete="off" />
                    <x-input label="Board" name="twelfthBoard" wire:model="twelfthBoard" autocomplete="off" />
                    <x-input label="Year of passing" name="twelfthYearOfPassing" wire:model="twelfthYearOfPassing" type="number" inputmode="numeric" />
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <x-select label="Result status" name="twelfthResultStatus" wire:model="twelfthResultStatus" placeholder="Not specified">
                        @foreach ($academicResultStatuses as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </x-select>

                    <x-select label="Marking scheme" name="twelfthMarkingScheme" wire:model="twelfthMarkingScheme" placeholder="Not specified">
                        @foreach ($markingSchemes as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </x-select>

                    <x-input
                        label="Percentage/CGPA"
                        name="twelfthScore"
                        wire:model="twelfthScore"
                        type="number"
                        step="0.01"
                        inputmode="decimal"
                    />
                </div>
            </div>

            <div>
                <p class="text-sm font-medium text-neutral-900">Graduation</p>

                <div class="mt-3 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <x-input label="University name" name="graduationUniversityName" wire:model="graduationUniversityName" autocomplete="off" />
                    <x-input label="Name of degree" name="graduationDegreeName" wire:model="graduationDegreeName" autocomplete="off" />
                    <x-input label="Year of passing" name="graduationYearOfPassing" wire:model="graduationYearOfPassing" type="number" inputmode="numeric" />
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <x-select label="Result status" name="graduationResultStatus" wire:model="graduationResultStatus" placeholder="Not specified">
                        @foreach ($academicResultStatuses as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </x-select>

                    <x-select label="Marking scheme" name="graduationMarkingScheme" wire:model="graduationMarkingScheme" placeholder="Not specified">
                        @foreach ($markingSchemes as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </x-select>

                    <x-input
                        label="Percentage/CGPA"
                        name="graduationScore"
                        wire:model="graduationScore"
                        type="number"
                        step="0.01"
                        inputmode="decimal"
                    />
                </div>
            </div>

            <div class="flex items-center gap-3">
                <x-button type="submit" wire:loading.attr="disabled">Save details</x-button>
                <span wire:loading wire:target="saveDetails" class="text-sm text-neutral-500">Saving…</span>
            </div>
        </form>
    </x-card>

    <x-card>
        <x-slot:header>
            <h2 class="text-lg text-teal-600">Email Address</h2>
            <p class="mt-1 text-sm text-neutral-500">Changing this signs you out until you confirm the new address.</p>
        </x-slot:header>
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
