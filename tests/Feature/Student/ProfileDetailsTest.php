<?php

declare(strict_types=1);

use App\Livewire\Student\ProfileForm;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Profile details — split name, required mobile, certificate name
|--------------------------------------------------------------------------
|
| Three changes, each with a rule that is easy to get subtly wrong:
|
|   `name` is DERIVED from the parts, in one place, so the greeting in a
|   learner's next email cannot disagree with what they just typed.
|
|   The mobile number is required BY THE FORM and nullable IN THE DATABASE.
|   That asymmetry is deliberate — an administrator creating a student on a
|   phone call has no form to fill in.
|
|   The certificate name is optional, and blank must store NULL rather than an
|   empty string, or the fallback that keeps a certificate from printing a
|   blank line never fires.
|
*/

beforeEach(function (): void {
    $this->student = User::factory()->student()->create([
        'name' => 'Dev Student',
        'first_name' => null,
        'last_name' => null,
        'certificate_name' => null,
        'phone' => null,
    ]);
});

/*
| ═══════════════ FIRST AND LAST NAME ═══════════════
*/
it('seeds the two name fields by splitting the existing display name', function (): void {
    $this->actingAs($this->student);

    // Every account predating these columns has only `name`. Showing a learner
    // two empty boxes when the system already knows their name is a poor way
    // to ask for something it could have offered.
    Livewire::test(ProfileForm::class)
        ->assertSet('firstName', 'Dev')
        ->assertSet('lastName', 'Student');
});

it('prefers the stored parts over splitting the display name', function (): void {
    $this->student->forceFill(['first_name' => 'Devendra', 'last_name' => 'Kulkarni'])->save();

    $this->actingAs($this->student);

    // The split is only a starting point. Once a learner has corrected it,
    // their answer wins — names do not divide reliably on spaces.
    Livewire::test(ProfileForm::class)
        ->assertSet('firstName', 'Devendra')
        ->assertSet('lastName', 'Kulkarni');
});

it('saves both parts and keeps the display name in step', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('firstName', 'Anita')
        ->set('lastName', 'Desai')
        ->set('phone', '+91 98765 43210')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $this->student->refresh();

    // `name` is what every email greeting and admin table reads. If it drifted,
    // a learner would rename themselves here and still be greeted by the old
    // name in their next email.
    expect($this->student->first_name)->toBe('Anita')
        ->and($this->student->last_name)->toBe('Desai')
        ->and($this->student->name)->toBe('Anita Desai');
});

it('requires both parts', function (string $field): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set($field, '')
        ->call('saveDetails')
        ->assertHasErrors($field);
})->with(['firstName', 'lastName']);

it('trims surrounding whitespace out of the assembled name', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('firstName', '  Anita  ')
        ->set('lastName', '  Desai ')
        ->set('phone', '9876543210')
        ->call('saveDetails');

    expect($this->student->refresh()->name)->toBe('Anita Desai');
});

/*
| ═══════════════ THE MOBILE NUMBER IS NOW REQUIRED ═══════════════
*/
it('refuses to save details without a mobile number', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('firstName', 'Anita')
        ->set('lastName', 'Desai')
        ->set('phone', '')
        ->call('saveDetails')
        ->assertHasErrors('phone');

    // Nothing partial: the name must not save while the phone is rejected.
    expect($this->student->refresh()->first_name)->toBeNull();
});

it('rejects a number too short to be one', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('firstName', 'Anita')
        ->set('lastName', 'Desai')
        ->set('phone', '123')
        ->call('saveDetails')
        ->assertHasErrors('phone');
});

it('rejects letters in a phone number', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('firstName', 'Anita')
        ->set('lastName', 'Desai')
        ->set('phone', 'call me maybe')
        ->call('saveDetails')
        ->assertHasErrors('phone');
});

it('accepts the punctuation people actually type', function (string $number): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('firstName', 'Anita')
        ->set('lastName', 'Desai')
        ->set('phone', $number)
        ->call('saveDetails')
        ->assertHasNoErrors();
})->with([
    'plain' => '9876543210',
    'with country code' => '+91 98765 43210',
    'with dashes' => '98765-43210',
    'with brackets' => '(022) 2201 1234',
]);

it('leaves the database column nullable, whatever the form requires', function (): void {
    /*
     * The asymmetry is the point. An administrator creating a student on a
     * phone call, and a Phase 12 purchase, both make accounts with no form
     * involved — a NOT NULL constraint would break them and every row that
     * predates the rule.
     */
    $created = User::factory()->student()->create(['phone' => null]);

    expect($created->refresh()->phone)->toBeNull();
});

/*
| ═══════════════ CERTIFICATE NAME ═══════════════
*/
it('stores the name a learner wants on their certificate', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('firstName', 'Anita')
        ->set('lastName', 'Desai')
        ->set('phone', '9876543210')
        ->set('certificateName', 'Dr Anita R. Desai')
        ->call('saveDetails')
        ->assertHasNoErrors();

    // Deliberately not derived from the parts: a formal name belongs on a
    // credential, and it is not always the name someone uses day to day.
    expect($this->student->refresh()->certificate_name)->toBe('Dr Anita R. Desai')
        ->and($this->student->name)->toBe('Anita Desai');
});

it('stores a blank certificate name as null, not an empty string', function (): void {
    $this->student->forceFill(['certificate_name' => 'Dr Anita R. Desai'])->save();

    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('firstName', 'Anita')
        ->set('lastName', 'Desai')
        ->set('phone', '9876543210')
        ->set('certificateName', '   ')
        ->call('saveDetails');

    // An empty string would satisfy the fallback check and print a blank line
    // where a name belongs. Null is what "no preference" has to mean.
    expect($this->student->refresh()->certificate_name)->toBeNull();
});

it('falls back to the display name when no preference is recorded', function (): void {
    expect($this->student->certificateName())->toBe('Dev Student');
});

it('uses the stated preference when there is one', function (): void {
    $this->student->forceFill(['certificate_name' => 'Devendra R. Kulkarni'])->save();

    expect($this->student->refresh()->certificateName())->toBe('Devendra R. Kulkarni');
});

it('treats a whitespace-only preference as none at all', function (): void {
    $this->student->forceFill(['certificate_name' => '   '])->save();

    expect($this->student->refresh()->certificateName())->toBe('Dev Student');
});

/*
| ═══════════════ AN ACCOUNT THAT NEVER SAW THIS FORM ═══════════════
|
| Registration, administrator creation and (Phase 12) purchase all build a user
| from name/email/password and never mention these three columns. `create()`
| does not re-read the row, so those columns are ABSENT from the in-memory model
| rather than null — and `preventAccessingMissingAttributes()` makes reading one
| throw outside production. A certificate name that works in production and
| throws in CI is the worst possible arrangement, so it is pinned here.
|
*/
it('answers for its certificate name on a just-created account', function (): void {
    $fresh = User::create([
        'name' => 'Ravi Menon',
        'email' => 'ravi.menon@example.test',
        'password' => 'password-not-used-here',
    ]);

    // No refresh() — that is the point. This is the instance the creating code
    // holds, with the three columns never set.
    expect($fresh->certificateName())->toBe('Ravi Menon')
        ->and($fresh->statedCertificateName())->toBeNull()
        ->and($fresh->assembledName())->toBeNull()
        ->and($fresh->editableNameParts())->toBe(['first' => 'Ravi', 'last' => 'Menon']);
});

it('opens the profile form for an account created without name parts', function (): void {
    $this->actingAs(User::factory()->student()->create(['name' => 'Ravi Menon']));

    Livewire::test(ProfileForm::class)
        ->assertSet('firstName', 'Ravi')
        ->assertSet('lastName', 'Menon')
        ->assertSet('certificateName', null);
});

it('splits a single-word name without inventing a last name', function (): void {
    $this->student->forceFill(['name' => 'Prince'])->save();

    // Str::after returns the whole string when the delimiter is absent, which
    // would have put "Prince" in BOTH boxes.
    expect($this->student->refresh()->editableNameParts())
        ->toBe(['first' => 'Prince', 'last' => '']);
});

/*
| ═══════════════ THE SCREEN ═══════════════
*/
it('shows all three fields with the mobile marked required', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->assertSee('First name')
        ->assertSee('Last name')
        ->assertSee('Mobile number')
        ->assertSee('Name for your certificate')
        // The hint says WHY it is needed. "Required" with no reason reads as
        // the form being nosy.
        ->assertSee('So we can reach you');
});

it('records the change in the audit log', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('firstName', 'Anita')
        ->set('lastName', 'Desai')
        ->set('phone', '9876543210')
        ->call('saveDetails');

    expect(App\Models\AuditLog::query()->where('action', 'profile.updated')->exists())->toBeTrue();
});

/*
| ═══════════════ OPTIONAL PERSONAL DETAILS ═══════════════
|
| Title, gender, date of birth, blood group, marital status, nationality.
| All optional (NFR-DATA-01): saving a name must never demand any of them.
*/
it('still saves name and phone with every personal detail left blank', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('firstName', 'Anita')
        ->set('lastName', 'Desai')
        ->set('phone', '9876543210')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->name)->toBe('Anita Desai')
        ->and($fresh->title)->toBeNull()
        ->and($fresh->gender)->toBeNull()
        ->and($fresh->date_of_birth)->toBeNull()
        ->and($fresh->blood_group)->toBeNull()
        ->and($fresh->marital_status)->toBeNull()
        ->and($fresh->nationality)->toBeNull();
});

it('saves every personal detail', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('personTitle', 'mr')
        ->set('gender', 'male')
        ->set('dateOfBirth', '2008-04-01')
        ->set('bloodGroup', 'A+')
        ->set('maritalStatus', 'single')
        ->set('nationality', ' Indian ')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->title)->toBe(App\Enums\PersonTitle::Mr)
        ->and($fresh->gender)->toBe(App\Enums\Gender::Male)
        ->and($fresh->date_of_birth?->format('Y-m-d'))->toBe('2008-04-01')
        ->and($fresh->blood_group)->toBe(App\Enums\BloodGroup::APositive)
        ->and($fresh->marital_status)->toBe(App\Enums\MaritalStatus::Single)
        ->and($fresh->nationality)->toBe('Indian');
});

it('loads stored personal details back into the form', function (): void {
    $this->student->forceFill([
        'title' => 'dr',
        'gender' => 'female',
        'date_of_birth' => '1990-06-15',
        'blood_group' => 'O-',
        'marital_status' => 'married',
        'nationality' => 'Indian',
    ])->save();

    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->assertSet('personTitle', 'dr')
        ->assertSet('gender', 'female')
        ->assertSet('dateOfBirth', '1990-06-15')
        ->assertSet('bloodGroup', 'O-')
        ->assertSet('maritalStatus', 'married')
        ->assertSet('nationality', 'Indian');
});

it('stores a cleared selection as null', function (): void {
    $this->student->forceFill(['gender' => 'male'])->save();

    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('gender', '')
        ->call('saveDetails')
        ->assertHasNoErrors();

    expect($this->student->refresh()->gender)->toBeNull();
});

it('rejects a value outside each list', function (string $field, string $value): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set($field, $value)
        ->call('saveDetails')
        ->assertHasErrors([$field]);
})->with([
    'title' => ['personTitle', 'sir'],
    'gender' => ['gender', 'unknown'],
    'blood group' => ['bloodGroup', 'C+'],
    'marital status' => ['maritalStatus', 'engaged'],
]);

it('rejects a date of birth in the future', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('dateOfBirth', now()->addYear()->format('Y-m-d'))
        ->call('saveDetails')
        ->assertHasErrors(['dateOfBirth']);
});

it('refuses an out-of-list value at the database too', function (): void {
    // The CHECK constraint is the backstop for any write path that skips the
    // form's validation.
    expect(fn () => Illuminate\Support\Facades\DB::table('users')
        ->where('id', $this->student->id)
        ->update(['blood_group' => 'C+']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('shows the age as on the most recent 1 April', function (): void {
    $this->travelTo(Carbon\CarbonImmutable::parse('2026-09-28'));

    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('dateOfBirth', '2008-04-01')
        ->assertSee('18 years, 0 months, 0 days')
        ->set('dateOfBirth', '2000-01-15')
        ->assertSee('26 years, 2 months, 17 days');
});

it('counts age to the previous 1 April before this year\'s has arrived', function (): void {
    $this->travelTo(Carbon\CarbonImmutable::parse('2026-02-10'));

    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('dateOfBirth', '2008-04-01')
        ->assertSee('17 years, 0 months, 0 days');
});

it('shows no age for an impossible date instead of failing', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('dateOfBirth', '2008-02-31')
        ->assertOk()
        ->assertDontSee('years,');
});

it('shows the personal details section', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->assertSee('Personal Details')
        ->assertSee('All optional')
        ->assertSee('Date of birth')
        ->assertSee('Age as on 1st April')
        ->assertSee('Blood group')
        ->assertSee('Marital status')
        ->assertSee('Nationality');
});

/*
| ═══════════════ ADDRESS ═══════════════
|
| Free-text country/state/district/city (no places dataset — see the
| migration), all optional, plus a "same as correspondence" toggle that
| decides whether a second address is asked for or stored at all.
*/
it('saves an address with every field left blank', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->address_country)->toBeNull()
        ->and($fresh->address_line_1)->toBeNull()
        ->and($fresh->address_pincode)->toBeNull()
        ->and($fresh->permanent_address_same_as_correspondence)->toBeTrue();
});

it('saves a correspondence address', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('addressCountry', 'India')
        ->set('addressState', 'Madhya Pradesh')
        ->set('addressDistrict', 'Bhopal')
        ->set('addressCity', 'Bhopal')
        ->set('addressLine1', '37, Yadav Society')
        ->set('addressPincode', '390819')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->address_country)->toBe('India')
        ->and($fresh->address_state)->toBe('Madhya Pradesh')
        ->and($fresh->address_district)->toBe('Bhopal')
        ->and($fresh->address_city)->toBe('Bhopal')
        ->and($fresh->address_line_1)->toBe('37, Yadav Society')
        ->and($fresh->address_pincode)->toBe('390819')
        ->and($fresh->address_line_2)->toBeNull();
});

it('accepts 0 as a pincode for a country that has none', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('addressPincode', '0')
        ->call('saveDetails')
        ->assertHasNoErrors();

    expect($this->student->refresh()->address_pincode)->toBe('0');
});

it('loads a stored address back into the form', function (): void {
    $this->student->forceFill([
        'address_country' => 'India',
        'address_city' => 'Bhopal',
        'address_line_1' => '37, Yadav Society',
        'address_pincode' => '390819',
    ])->save();

    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->assertSet('addressCountry', 'India')
        ->assertSet('addressCity', 'Bhopal')
        ->assertSet('addressLine1', '37, Yadav Society')
        ->assertSet('addressPincode', '390819');
});

it('defaults the permanent address to "same as correspondence"', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)->assertSet('permanentSameAsCorrespondence', true);
});

it('hides the permanent address block until "No" is chosen', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->assertDontSee('Permanent address')
        ->set('permanentSameAsCorrespondence', false)
        ->assertSee('Permanent address');
});

it('discards a typed permanent address when the toggle is switched back to yes', function (): void {
    $this->actingAs($this->student);

    // Typed into the block, then the toggle is switched back to "yes" before
    // saving — the typed values must not survive that.
    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('permanentSameAsCorrespondence', false)
        ->set('permanentCountry', 'India')
        ->set('permanentLine1', 'Somewhere else')
        ->set('permanentPincode', '110001')
        ->set('permanentSameAsCorrespondence', true)
        ->call('saveDetails')
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->permanent_address_same_as_correspondence)->toBeTrue()
        ->and($fresh->permanent_address_country)->toBeNull()
        ->and($fresh->permanent_address_line_1)->toBeNull()
        ->and($fresh->permanent_address_pincode)->toBeNull();
});

it('saves a permanent address that differs from the correspondence one', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('addressCountry', 'India')
        ->set('addressCity', 'Bhopal')
        ->set('permanentSameAsCorrespondence', false)
        ->set('permanentCountry', 'India')
        ->set('permanentState', 'Maharashtra')
        ->set('permanentCity', 'Pune')
        ->set('permanentLine1', '12, MG Road')
        ->set('permanentPincode', '411001')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->permanent_address_same_as_correspondence)->toBeFalse()
        ->and($fresh->permanent_address_country)->toBe('India')
        ->and($fresh->permanent_address_state)->toBe('Maharashtra')
        ->and($fresh->permanent_address_city)->toBe('Pune')
        ->and($fresh->permanent_address_line_1)->toBe('12, MG Road')
        ->and($fresh->permanent_address_pincode)->toBe('411001')
        // The correspondence address is untouched by the permanent one.
        ->and($fresh->address_country)->toBe('India')
        ->and($fresh->address_city)->toBe('Bhopal');
});

it('requires country, address line 1 and pincode once the permanent address differs', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('permanentSameAsCorrespondence', false)
        ->call('saveDetails')
        ->assertHasErrors(['permanentCountry', 'permanentLine1', 'permanentPincode']);

    expect($this->student->refresh()->permanent_address_same_as_correspondence)->toBeTrue();
});

it('does not require a permanent address while it is the same as correspondence', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->call('saveDetails')
        ->assertHasNoErrors();
});

it('loads a stored differing permanent address back into the form', function (): void {
    $this->student->forceFill([
        'permanent_address_same_as_correspondence' => false,
        'permanent_address_country' => 'India',
        'permanent_address_city' => 'Pune',
        'permanent_address_line_1' => '12, MG Road',
        'permanent_address_pincode' => '411001',
    ])->save();

    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->assertSet('permanentSameAsCorrespondence', false)
        ->assertSet('permanentCountry', 'India')
        ->assertSet('permanentCity', 'Pune')
        ->assertSet('permanentLine1', '12, MG Road')
        ->assertSet('permanentPincode', '411001')
        ->assertSee('Permanent address');
});

it('shows the address section', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->assertSee('Address')
        ->assertSee('Address for correspondence')
        ->assertSee('Is your permanent address the same as your address for correspondence?')
        ->assertSee('Pincode');
});

/*
| ═══════════════ AADHAAR AND PAN ═══════════════
|
| Government identity numbers. Beyond "does it save", these tests pin down
| the protections: encrypted at rest, never shown in full, never loaded into
| the form, never in the audit log, never serialised.
|
| 234123412346 has a valid Verhoeff check digit; 234123412347 does not.
*/
it('saves an Aadhaar number typed in groups, and a lower-case PAN', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('aadhaarNumber', '2341 2341 2346')
        ->set('panNumber', 'abcde1234f')
        ->call('saveDetails')
        ->assertHasNoErrors()
        // Cleared after saving: a typed number must not linger in state.
        ->assertSet('aadhaarNumber', '')
        ->assertSet('panNumber', '');

    $fresh = $this->student->refresh();

    expect($fresh->aadhaar_number)->toBe('234123412346')
        ->and($fresh->pan_number)->toBe('ABCDE1234F');
});

it('stores both numbers encrypted, never as plain text', function (): void {
    $this->student->forceFill(['aadhaar_number' => '234123412346', 'pan_number' => 'ABCDE1234F'])->save();

    // Read past the model, straight from the table — what a database dump or
    // a SQL session would see.
    $row = Illuminate\Support\Facades\DB::table('users')->where('id', $this->student->id);

    expect((string) $row->clone()->value('aadhaar_number'))->not->toBe('')->not->toContain('234123412346')
        ->and((string) $row->clone()->value('pan_number'))->not->toBe('')->not->toContain('ABCDE1234F');
});

it('rejects an Aadhaar number with a wrong check digit', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('aadhaarNumber', '234123412347')
        ->call('saveDetails')
        ->assertHasErrors(['aadhaarNumber']);

    expect($this->student->refresh()->aadhaar_number)->toBeNull();
});

it('rejects a malformed Aadhaar number', function (string $number): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('aadhaarNumber', $number)
        ->call('saveDetails')
        ->assertHasErrors(['aadhaarNumber']);
})->with([
    'too short' => ['23412341234'],
    'starts with 1' => ['134123412346'],
    'letters' => ['2341234123AB'],
]);

it('rejects a malformed PAN', function (string $pan): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('panNumber', $pan)
        ->call('saveDetails')
        ->assertHasErrors(['panNumber']);
})->with([
    'too short' => ['ABCDE123F'],
    'digits where letters go' => ['12345ABCDF'],
]);

it('never loads a stored number into the form, and shows it only masked', function (): void {
    $this->student->forceFill(['aadhaar_number' => '234123412346', 'pan_number' => 'ABCDE1234F'])->save();

    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->assertSet('aadhaarNumber', '')
        ->assertSet('panNumber', '')
        ->assertSee('XXXX XXXX 2346')
        ->assertSee('XXXXXX234F')
        ->assertDontSee('234123412346')
        ->assertDontSee('ABCDE1234F');
});

it('keeps the stored numbers when the fields are left blank', function (): void {
    $this->student->forceFill(['aadhaar_number' => '234123412346', 'pan_number' => 'ABCDE1234F'])->save();

    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->aadhaar_number)->toBe('234123412346')
        ->and($fresh->pan_number)->toBe('ABCDE1234F');
});

it('removes a stored number only on the explicit remove action', function (): void {
    $this->student->forceFill(['aadhaar_number' => '234123412346', 'pan_number' => 'ABCDE1234F'])->save();

    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)->call('removeAadhaar');

    $fresh = $this->student->refresh();

    expect($fresh->aadhaar_number)->toBeNull()
        ->and($fresh->pan_number)->toBe('ABCDE1234F');

    Livewire::test(ProfileForm::class)->call('removePan');

    expect($this->student->refresh()->pan_number)->toBeNull();
});

it('never writes an identity number to the audit log', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('aadhaarNumber', '234123412346')
        ->set('panNumber', 'ABCDE1234F')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $entry = App\Models\AuditLog::query()->where('action', 'profile.updated')->latest('id')->firstOrFail();
    $logged = json_encode($entry->getAttributes());

    expect($logged)->not->toContain('234123412346')
        ->and($logged)->not->toContain('ABCDE1234F')
        ->and($logged)->toContain('[redacted]');
});

it('never serialises an identity number', function (): void {
    $this->student->forceFill(['aadhaar_number' => '234123412346', 'pan_number' => 'ABCDE1234F'])->save();

    expect($this->student->refresh()->toArray())
        ->not->toHaveKey('aadhaar_number')
        ->not->toHaveKey('pan_number');
});

/*
| ═══════════════ ACADEMIC DETAILS ═══════════════
|
| Three fixed stages (Class Xth, Class XIIth, Graduation), all
| optional, plus "after Xth qualification" which is recorded but never
| changes what the form shows — see ProfileForm's class docblock.
*/
it('saves academic details with every field left blank', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->tenth_school_name)->toBeNull()
        ->and($fresh->tenth_year_of_passing)->toBeNull()
        ->and($fresh->after_tenth_qualification)->toBeNull()
        ->and($fresh->graduation_university_name)->toBeNull();
});

it('saves Class Xth details', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('tenthSchoolName', 'Anand Vidya Sankul')
        ->set('tenthBoard', 'CBSE')
        ->set('tenthYearOfPassing', '2021')
        ->set('tenthMarkingScheme', 'percentage')
        ->set('tenthScore', '85.34')
        ->set('afterTenthQualification', 'class_xii_hsc')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->tenth_school_name)->toBe('Anand Vidya Sankul')
        ->and($fresh->tenth_board)->toBe('CBSE')
        ->and($fresh->tenth_year_of_passing)->toBe(2021)
        ->and($fresh->tenth_marking_scheme)->toBe(App\Enums\MarkingScheme::Percentage)
        ->and((float) $fresh->tenth_score)->toBe(85.34)
        ->and($fresh->after_tenth_qualification)->toBe(App\Enums\AfterTenthQualification::ClassXiiHsc);
});

it('saves Class XIIth details', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('twelfthSchoolName', 'Anand Vidya Sankul')
        ->set('twelfthBoard', 'CBSE')
        ->set('twelfthYearOfPassing', '2023')
        ->set('twelfthResultStatus', 'declared')
        ->set('twelfthMarkingScheme', 'percentage')
        ->set('twelfthScore', '84.92')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->twelfth_school_name)->toBe('Anand Vidya Sankul')
        ->and($fresh->twelfth_year_of_passing)->toBe(2023)
        ->and($fresh->twelfth_result_status)->toBe(App\Enums\AcademicResultStatus::Declared)
        ->and((float) $fresh->twelfth_score)->toBe(84.92);
});

it('saves Graduation details with a result still awaited', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('graduationUniversityName', 'Nirma University')
        ->set('graduationDegreeName', 'BCA')
        ->set('graduationYearOfPassing', (string) now()->addMonths(6)->year)
        ->set('graduationResultStatus', 'awaited')
        ->set('graduationMarkingScheme', 'cgpa')
        ->set('graduationScore', '6.01')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->graduation_university_name)->toBe('Nirma University')
        ->and($fresh->graduation_degree_name)->toBe('BCA')
        ->and($fresh->graduation_result_status)->toBe(App\Enums\AcademicResultStatus::Awaited)
        ->and($fresh->graduation_marking_scheme)->toBe(App\Enums\MarkingScheme::Cgpa)
        ->and((float) $fresh->graduation_score)->toBe(6.01);
});

it('rejects a score above the bound for the chosen marking scheme', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('graduationMarkingScheme', 'cgpa')
        // Valid as a percentage, not as a CGPA (max 10).
        ->set('graduationScore', '85.34')
        ->call('saveDetails')
        ->assertHasErrors(['graduationScore']);
});

it('accepts a score up to 100 for a percentage', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set('tenthMarkingScheme', 'percentage')
        ->set('tenthScore', '100')
        ->call('saveDetails')
        ->assertHasNoErrors();
});

it('rejects a value outside each academic list', function (string $field, string $value): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set($field, $value)
        ->call('saveDetails')
        ->assertHasErrors([$field]);
})->with([
    'marking scheme' => ['tenthMarkingScheme', 'grade'],
    'result status' => ['twelfthResultStatus', 'pending'],
    'after-Xth qualification' => ['afterTenthQualification', 'university'],
]);

it('rejects a year of passing too far in the past or future', function (string $field, string $value): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->set('phone', '9876543210')
        ->set($field, $value)
        ->call('saveDetails')
        ->assertHasErrors([$field]);
})->with([
    'too old' => ['tenthYearOfPassing', '1899'],
    'too far ahead' => ['graduationYearOfPassing', (string) now()->addYears(20)->year],
    'not a number' => ['twelfthYearOfPassing', 'MMXXIII'],
]);

it('refuses an out-of-list marking scheme at the database too', function (): void {
    expect(fn () => Illuminate\Support\Facades\DB::table('users')
        ->where('id', $this->student->id)
        ->update(['tenth_marking_scheme' => 'grade']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('loads stored academic details back into the form', function (): void {
    $this->student->forceFill([
        'tenth_school_name' => 'Anand Vidya Sankul',
        'tenth_board' => 'CBSE',
        'tenth_year_of_passing' => 2021,
        'tenth_marking_scheme' => 'percentage',
        'tenth_score' => '85.34',
        'after_tenth_qualification' => 'class_xii_hsc',
        'graduation_university_name' => 'Nirma University',
        'graduation_result_status' => 'awaited',
    ])->save();

    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->assertSet('tenthSchoolName', 'Anand Vidya Sankul')
        ->assertSet('tenthBoard', 'CBSE')
        ->assertSet('tenthYearOfPassing', '2021')
        ->assertSet('tenthMarkingScheme', 'percentage')
        ->assertSet('tenthScore', '85.34')
        ->assertSet('afterTenthQualification', 'class_xii_hsc')
        ->assertSet('graduationUniversityName', 'Nirma University')
        ->assertSet('graduationResultStatus', 'awaited');
});

it('does not let after-Xth qualification change which fields are shown', function (): void {
    $this->actingAs($this->student);

    // Whatever is chosen, Class XIIth and Graduation stay visible — see
    // ProfileForm's class docblock on why this does not branch.
    Livewire::test(ProfileForm::class)
        ->set('afterTenthQualification', 'diploma')
        ->assertSee('Class XIIth')
        ->assertSee('Graduation')
        ->set('afterTenthQualification', 'class_xii_hsc')
        ->assertSee('Class XIIth')
        ->assertSee('Graduation');
});

it('shows the academic details section', function (): void {
    $this->actingAs($this->student);

    Livewire::test(ProfileForm::class)
        ->assertSee('Academic Details')
        ->assertSee('Class Xth')
        ->assertSee('After Xth qualification')
        ->assertSee('Class XIIth')
        ->assertSee('Graduation')
        ->assertSee('Percentage/CGPA');
});
