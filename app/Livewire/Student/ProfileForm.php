<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Actions\Profile\ChangeEmail;
use App\Actions\Profile\UpdateProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Editable profile details (FR-STU-12, FR-AUTH-12).
 *
 * Sits inside the existing profile page alongside the read-only account card
 * and Fortify's password form, which are unchanged. Password changes stay with
 * Fortify — writing a second password path here would be a second door.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * CHANGING THE EMAIL REQUIRES THE CURRENT PASSWORD. THE NAME DOES NOT.
 *
 * They are different risks. A wrong name is cosmetic. A changed email moves
 * the address every password-reset link and notification goes to — it is the
 * step an account takeover needs, and the one a borrowed unlocked laptop makes
 * trivial.
 *
 * The password prompt is also the only protection against a typo, because
 * ChangeEmail writes immediately and sends verification to the NEW address:
 * get it wrong and the recovery path goes somewhere you do not control.
 *
 * Asking for it on every profile edit would train people to type their
 * password without reading. Asking only where it matters keeps it meaningful.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class ProfileForm extends Component
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:32')]
    public ?string $phone = null;

    public string $email = '';

    /** Cleared after every submit — never held in component state longer. */
    public string $currentPassword = '';

    public function mount(): void
    {
        $user = $this->user();

        $this->name = $user->name;
        $this->phone = $user->phone;
        $this->email = $user->email;
    }

    /**
     * Name and phone. No password required — see the class docblock.
     */
    public function saveDetails(): void
    {
        $this->validateOnly('name');
        $this->validateOnly('phone');

        app(UpdateProfile::class)->handle($this->user(), [
            'name' => $this->name,
            'phone' => $this->phone,
        ]);

        session()->flash('status', 'Your details have been updated.');
    }

    /**
     * Email, gated on the current password.
     */
    public function saveEmail(): void
    {
        $user = $this->user();

        $this->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                // Ignores this user's own row so re-submitting an unchanged
                // address reports "already yours" rather than "taken".
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
            'currentPassword' => ['required', 'string'],
        ], attributes: ['currentPassword' => 'current password']);

        $hashed = $user->password;

        // An account still awaiting activation has NO password (ADR-004), and
        // Hash::check against null would be a type error rather than a refusal.
        // Refuse explicitly: someone who has never set a password cannot prove
        // they are present, which is exactly what this gate is checking for.
        if ($hashed === null || ! Hash::check($this->currentPassword, $hashed)) {
            // Cleared before returning: a wrong password must not stay in the
            // component's serialised state to be replayed.
            $this->currentPassword = '';

            $this->addError('currentPassword', 'That password is incorrect.');

            return;
        }

        try {
            app(ChangeEmail::class)->handle($user, $this->email);
        } catch (InvalidArgumentException $e) {
            $this->addError('email', $e->getMessage());

            return;
        } finally {
            $this->currentPassword = '';
        }

        session()->flash('status', 'Check your new address — we have sent a verification link. You will need it before signing in again.');
    }

    public function render(): View
    {
        return view('livewire.student.profile-form');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
