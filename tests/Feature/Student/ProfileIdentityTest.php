<?php

declare(strict_types=1);

use App\Livewire\Student\ProfileIdentity;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The profile page's identity card
|--------------------------------------------------------------------------
|
| A Livewire component specifically so it can be told a photo just changed —
| see ProfileIdentity's class docblock. The central thing this file proves is
| that ->dispatch('profile-photo-updated'), the actual event
| ProfileDocumentsForm fires, makes this card show a newly stored photo
| without anything else re-rendering it.
*/

beforeEach(function (): void {
    $this->student = User::factory()->student()->create(['name' => 'Dev Student']);
    $this->actingAs($this->student);
});

it('shows the initials disc when no photo is stored', function (): void {
    Livewire::test(ProfileIdentity::class)
        ->assertSee('DS')
        ->assertDontSeeHtml('<img');
});

it('shows the stored photo instead of initials', function (): void {
    $this->student->forceFill(['avatar_path' => 'avatars/1/me.jpg.enc'])->save();

    Livewire::test(ProfileIdentity::class)->assertSeeHtml('<img');
});

it('picks up a newly stored photo when told to refresh', function (): void {
    $component = Livewire::test(ProfileIdentity::class)
        ->assertDontSeeHtml('<img');

    // The photo is stored by a separate request in the real flow
    // (ProfileDocumentsForm); simulated here by writing it directly, since
    // what this test is proving is that THIS component notices, not that
    // uploading works — that is ProfileDocumentsTest's job.
    $this->student->forceFill(['avatar_path' => 'avatars/1/me.jpg.enc'])->save();

    // The actual event ProfileDocumentsForm dispatches — not calling
    // refresh() by name, so this also proves the #[On(...)] attribute is
    // wired to the right event string.
    $component->dispatch('profile-photo-updated')->assertSeeHtml('<img');
});

it('shows the name, email, join date, role and status', function (): void {
    Livewire::test(ProfileIdentity::class)
        ->assertSee('Dev Student')
        ->assertSee($this->student->email)
        ->assertSee('Learning since')
        ->assertSee($this->student->role->label())
        ->assertSee($this->student->status->label());
});
