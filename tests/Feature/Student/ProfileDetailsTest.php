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
