<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Student self-registration (FR-AUTH-01, FR-AUTH-11, FR-RBAC-07)
|--------------------------------------------------------------------------
*/

it('shows the registration page', function (): void {
    $this->get('/register')->assertOk()->assertSee('Create your account');
});

it('registers a student as pending_verification', function (): void {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Asha Rao',
        'email' => 'asha@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $user = User::query()->where('email', 'asha@example.com')->sole();

    expect($user->role)->toBe(UserRole::Student)
        ->and($user->status)->toBe(UserStatus::PendingVerification)
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->password)->not->toBeNull();

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('never stores the password in plain text', function (): void {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Asha Rao',
        'email' => 'asha@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $user = User::query()->where('email', 'asha@example.com')->sole();

    expect($user->password)->not->toBe('correct-horse-battery-staple')
        ->and($user->password)->toStartWith('$2y$');
});

/*
| PRIVILEGE ESCALATION (FR-RBAC-07, NFR-SEC-07).
|
| The single most valuable test in this file. Registration accepts name, email
| and password — nothing else. An attacker adding role/status to the payload
| must get an ordinary student account, not an administrator.
*/
it('ignores role and status supplied in the registration payload', function (): void {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Mallory',
        'email' => 'mallory@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'role' => 'super_admin',
        'status' => 'active',
    ]);

    $user = User::query()->where('email', 'mallory@example.com')->sole();

    expect($user->role)->toBe(UserRole::Student)
        ->and($user->status)->toBe(UserStatus::PendingVerification);
});

it('rejects a duplicate email regardless of casing', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post('/register', [
        'name' => 'Someone',
        'email' => 'TAKEN@Example.COM',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(1);
});

it('normalises the stored email to lower case', function (): void {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Asha Rao',
        'email' => '  ASHA@Example.COM  ',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    expect(User::query()->where('email', 'asha@example.com')->exists())->toBeTrue();
});

it('requires a confirmed password', function (): void {
    $this->post('/register', [
        'name' => 'Asha Rao',
        'email' => 'asha@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'something-else',
    ])->assertSessionHasErrors('password');

    expect(User::query()->count())->toBe(0);
});

it('writes an audit entry on registration', function (): void {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Asha Rao',
        'email' => 'asha@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    expect(AuditLog::query()->where('action', 'user.registered')->exists())->toBeTrue();
});
