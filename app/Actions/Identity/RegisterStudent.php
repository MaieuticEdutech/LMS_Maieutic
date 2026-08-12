<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Create a self-registered student account (FR-AUTH-01, FR-AUTH-11).
 *
 * THE SINGLE IMPLEMENTATION of student self-registration. Fortify's
 * CreateNewUser is a thin adapter over this, so registration behaves
 * identically whether it arrives via HTTP, a console command or a test
 * (ADR-013, P-5).
 *
 * SECURITY — role and status are set HERE, by name, and are never read from
 * input (FR-RBAC-07, NFR-SEC-07):
 *
 *   role   = Student always. There is no code path by which a registration
 *            request can produce an instructor or super admin, regardless of
 *            what the payload contains. `role` is not in User::$fillable, so
 *            even a mistaken mass-assign would throw rather than escalate.
 *
 *   status = PendingVerification. The account exists but cannot authenticate
 *            until the email address is proven, because only `active` passes
 *            the check inside Fortify::authenticateUsing().
 */
final class RegisterStudent
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function handle(array $attributes): User
    {
        $user = DB::transaction(function () use ($attributes): User {
            $user = new User;

            // Fillable, user-supplied.
            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
            ]);

            // NOT fillable — privilege fields, assigned explicitly.
            $user->role = UserRole::Student;
            $user->status = UserStatus::PendingVerification;

            $user->save();

            return $user;
        });

        $this->audit->record(
            action: 'user.registered',
            actor: $user,
            subject: $user,
            description: "Student self-registered with email {$user->email}.",
        );

        // Fortify's RegisteredResponse fires the Registered event, which
        // dispatches the verification email. Sent synchronously in Phase 2;
        // moved onto the `mail` queue in Phase 11 (FR-MAIL-06).
        return $user;
    }
}
