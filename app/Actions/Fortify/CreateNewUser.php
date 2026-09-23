<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Actions\Identity\RegisterStudent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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

    public function __construct(
        private readonly RegisterStudent $registerStudent,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        // Fortify's own /register route (unlike /login) has no built-in
        // config hook for attaching a rate limiter — it reads
        // config('fortify.limiters.login') for its login route, but has no
        // equivalent for registration. RateLimiter::for('register', ...) in
        // FortifyServiceProvider was defined since Phase 2 and never
        // actually reachable by any route, discovered in the Phase 14 audit.
        // Checked directly here instead, the same pattern AttemptRunner uses
        // for the same reason (a Livewire action route middleware can't see).
        $key = 'register:'.$this->request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many registration attempts. Please try again later.',
            ]);
        }

        RateLimiter::hit($key, 3600);

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
