<?php

declare(strict_types=1);

use App\Actions\Profile\UpdateProfile;
use App\Actions\Profile\UploadAadhaarDocument;
use App\Livewire\Student\ProfileDocumentsForm;
use App\Livewire\Student\ProfileForm;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Aadhaar and PAN collection can be switched off (config/profile.php)
|--------------------------------------------------------------------------
|
| Storing an Aadhaar does not verify anyone, and it is sensitive data on a
| server with no backups yet, so collection is OFF by default. The suite runs
| with it ON (phpunit.xml) so the existing profile tests keep covering that
| behaviour; every case here switches it off explicitly.
|
| Hiding a control is never the control, so the backend is tested directly as
| well as the screen. Removal must keep working whatever the switch says: a
| learner can always delete what they gave us.
|
| 234123412346 has a valid Verhoeff check digit.
*/

beforeEach(function (): void {
    Storage::fake('profile_documents');

    $this->student = User::factory()->student()->create();
    $this->actingAs($this->student);
});

it('is off by default in the shipped configuration', function (): void {
    // phpunit.xml defines the variable from OUTSIDE, which Laravel's immutable
    // env repository refuses to clear, so take it out of the three places PHP
    // keeps environment variables and put it back afterwards.
    $before = [getenv('PROFILE_COLLECT_IDENTITY'), $_ENV['PROFILE_COLLECT_IDENTITY'] ?? null, $_SERVER['PROFILE_COLLECT_IDENTITY'] ?? null];

    putenv('PROFILE_COLLECT_IDENTITY');
    unset($_ENV['PROFILE_COLLECT_IDENTITY'], $_SERVER['PROFILE_COLLECT_IDENTITY']);

    try {
        $config = require config_path('profile.php');
    } finally {
        putenv('PROFILE_COLLECT_IDENTITY='.($before[0] === false ? '' : $before[0]));
        $before[1] === null ?: $_ENV['PROFILE_COLLECT_IDENTITY'] = $before[1];
        $before[2] === null ?: $_SERVER['PROFILE_COLLECT_IDENTITY'] = $before[2];
    }

    expect($config['collect_identity'])->toBeFalse();
});

describe('with collection switched off', function (): void {
    beforeEach(function (): void {
        config()->set('profile.collect_identity', false);
    });

    it('shows no Aadhaar or PAN inputs and no Aadhaar upload', function (): void {
        Livewire::test(ProfileForm::class)
            ->assertDontSee('XXXX XXXX XXXX')
            ->assertDontSee('ABCDE1234F');

        Livewire::test(ProfileDocumentsForm::class)
            ->assertDontSee('Choose your Aadhaar card')
            ->assertDontSee('Aadhaar card')
            ->assertSee('Your profile photo.');
    });

    it('does not store a number typed into a crafted request', function (): void {
        Livewire::test(ProfileForm::class)
            ->set('phone', '9876543210')
            ->set('aadhaarNumber', '234123412346')
            ->set('panNumber', 'ABCDE1234F')
            ->call('saveDetails')
            ->assertHasNoErrors();

        $fresh = $this->student->refresh();

        expect($fresh->aadhaar_number)->toBeNull()
            ->and($fresh->pan_number)->toBeNull()
            // The rest of the form still saved.
            ->and($fresh->phone)->toBe('9876543210');
    });

    it('refuses a new number at the action, whoever calls it', function (): void {
        app(UpdateProfile::class)->handle($this->student, [
            'aadhaar_number' => '234123412346',
            'pan_number' => 'ABCDE1234F',
            'phone' => '9876543210',
        ]);

        $fresh = $this->student->refresh();

        expect($fresh->aadhaar_number)->toBeNull()
            ->and($fresh->pan_number)->toBeNull()
            ->and($fresh->phone)->toBe('9876543210');
    });

    it('refuses a new Aadhaar scan, in the component and in the action', function (): void {
        Livewire::test(ProfileDocumentsForm::class)
            ->set('aadhaarDocument', UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf'))
            ->assertForbidden();

        expect(fn () => app(UploadAadhaarDocument::class)->handle(
            $this->student,
            UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf'),
        ))->toThrow(DomainException::class);

        expect($this->student->refresh()->getAttributes()['aadhaar_document_path'])->toBeNull();
    });

    it('still shows a saved number masked, and still lets the learner delete it', function (): void {
        $this->student->forceFill(['aadhaar_number' => '234123412346', 'pan_number' => 'ABCDE1234F'])->save();

        Livewire::test(ProfileForm::class)
            ->assertSee('We no longer collect Aadhaar or PAN numbers')
            ->assertDontSee('234123412346')
            ->call('removeAadhaar')
            ->call('removePan');

        $fresh = $this->student->refresh();

        expect($fresh->aadhaar_number)->toBeNull()
            ->and($fresh->pan_number)->toBeNull();
    });

    it('still lets the learner see and delete a scan uploaded earlier', function (): void {
        config()->set('profile.collect_identity', true);
        app(UploadAadhaarDocument::class)->handle(
            $this->student,
            UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf'),
        );
        config()->set('profile.collect_identity', false);

        $name = $this->student->refresh()->getAttributes()['aadhaar_document_original_name'];

        Livewire::test(ProfileDocumentsForm::class)
            ->assertSee($name)
            ->assertSee('We no longer collect this')
            ->assertDontSee('Choose your Aadhaar card')
            ->call('removeAadhaarDocument');

        expect($this->student->refresh()->getAttributes()['aadhaar_document_path'])->toBeNull();
    });
});
