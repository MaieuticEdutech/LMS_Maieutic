<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Actions\Identity\RegisterStudent;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Fortify adapter — registration.
 *
 * ADR-013: Fortify's pipeline classes are kept where the framework expects
 * them, but each is a THIN ADAPTER that validates input and delegates to the
 * LMS domain Action. The behaviour lives in RegisterStudent, so it stays
 * callable from a job, a console command or a test without an HTTP request.
 *
 * NOTE WHAT IS NOT VALIDATED HERE: `role` and `status`. They are not accepted
 * as input at all — RegisterStudent assigns them. A payload containing
 * "role":"super_admin" is simply ignored, and because neither is in
 * User::$fillable, a future refactor that tried to mass-assign them would
 * throw rather than escalate (FR-RBAC-07, NFR-SEC-07).
 */
final class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private readonly RegisterStudent $registerStudent) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Lower-cased before uniqueness is checked so "A@x.com" cannot
                // slip past a unique index holding "a@x.com" (FR-AUTH-10).
                'unique:users,email',
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return $this->registerStudent->handle([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
    }
}
