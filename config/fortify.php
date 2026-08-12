<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Laravel Fortify — headless authentication backend
|--------------------------------------------------------------------------
|
| ADR-013 / C-06: Fortify supplies the security primitives (hashing, session
| handling, throttling, verification, password reset); the LMS supplies every
| view and the account-state rules on top.
|
| Feature selection is deliberate and matches architecture.md §7.1.1. Unused
| features are REMOVED, not left enabled and unused — an enabled feature is a
| live route and therefore attack surface (Phase 2 DoD).
|
*/

return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    /*
    | Emails are normalised to lower case before lookup and storage, so
    | A@X.com and a@x.com resolve to ONE account (FR-AUTH-10). The User model
    | mirrors this with a mutator so the invariant holds for records created
    | outside Fortify — e.g. purchase-created accounts in Phase 12.
    */
    'lowercase_usernames' => true,

    /*
    | Fallback redirect only. The real post-login destination is role-based
    | (super_admin -> /admin, instructor -> /instructor, student -> /dashboard)
    | and is resolved by App\Http\Responses\LoginResponse, bound in
    | FortifyServiceProvider (architecture.md §7.3).
    */
    'home' => '/dashboard',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    /*
    | Fortify's login limiter. Keyed on email + IP so one attacker cannot lock
    | out an entire IP's worth of legitimate users, and one victim's account
    | cannot be locked from arbitrary addresses (architecture.md §18.3,
    | NFR-SEC-10). Defined in FortifyServiceProvider.
    */
    'limiters' => [
        'login' => 'login',
    ],

    /*
    | Fortify registers its own view routes. Each one is bound to an LMS Blade
    | view in FortifyServiceProvider — no Fortify or starter-kit markup is used
    | anywhere in this application (C-06).
    */
    'views' => true,

    'features' => [
        // Student self-registration. The role is forced to `student`
        // server-side in CreateNewUser; it is never read from request input
        // (FR-RBAC-07, NFR-SEC-07).
        Features::registration(),

        // Serves BOTH password reset and first-time account activation. The
        // password broker stores tokens hashed, expiring and single-use, which
        // is precisely why the LMS has no bespoke activation table (ADR-004).
        Features::resetPasswords(),

        // ENABLED (Laravel ships this commented out). A self-registered
        // student must verify their email before the account becomes `active`
        // (FR-AUTH-11).
        Features::emailVerification(),

        // Used by the profile screen's change-password form.
        Features::updatePasswords(),

        /*
        | DELIBERATELY ABSENT — do not re-add without a recorded decision:
        |
        | Features::updateProfileInformation()
        |     The LMS owns profile updates through its own Action, because
        |     changing the login email must trigger re-verification
        |     (FR-STU-14) — behaviour Fortify's generic endpoint does not have.
        |
        | Features::twoFactorAuthentication()
        |     [V1.1] FR-AUTH-13. Enabled later by restoring this line, adding
        |     the two-factor columns migration and building the UI.
        |
        | Features::passkeys()
        |     Not in the V1 scope at all (requirements.md §4.2 — no SSO or
        |     alternative authentication mechanisms).
        */
    ],

];
