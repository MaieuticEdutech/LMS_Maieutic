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
| Read-only: initials, name, email, join date, role and status.
*/

beforeEach(function (): void {
    $this->student = User::factory()->student()->create(['name' => 'Dev Student']);
    $this->actingAs($this->student);
});

it('shows the initials disc', function (): void {
    Livewire::test(ProfileIdentity::class)
        ->assertSee('DS')
        ->assertDontSeeHtml('<img');
});

it('shows the name, email, join date, role and status', function (): void {
    Livewire::test(ProfileIdentity::class)
        ->assertSee('Dev Student')
        ->assertSee($this->student->email)
        ->assertSee('Learning since')
        ->assertSee($this->student->role->label())
        ->assertSee($this->student->status->label());
});
