<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Seeds the first Super Admin — the account that bootstraps the platform
 * (A-13, FR-RBAC-07).
 *
 * WHY A SEEDER AND NOT A REGISTRATION FORM:
 * The Super Admin role must not be assignable through any self-service path.
 * If it were, the highest-privilege role in the system would be one form post
 * away from anyone who found the endpoint. The first one is created at
 * deployment; further ones require a deliberate, audited action by an existing
 * Super Admin (Phase 4).
 *
 * PASSWORD HANDLING:
 * In local/testing, a known development password is used so the seeded account
 * is usable immediately.
 *
 * In any other environment a RANDOM password is generated and the account is
 * left at `pending_activation` with NO usable password, so the operator must
 * complete the "forgot password" flow to take control. That means no default
 * credential ever exists in production — the single most common way a seeded
 * admin account becomes an open door.
 *
 * Idempotent: re-running never creates a duplicate and never resets an
 * existing administrator's password.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // config(), never env(): once `config:cache` has run in a deployed
        // environment, env() returns NULL and this seeder would create an
        // administrator with an empty email (rule E-3).
        $email = mb_strtolower(config()->string('lms.super_admin.email'));
        $name = config()->string('lms.super_admin.name');

        if (User::query()->where('email', $email)->exists()) {
            $this->say("Super Admin [{$email}] already exists — skipping.");

            return;
        }

        $isLocal = app()->environment(['local', 'testing']);

        $user = new User;
        $user->fill([
            'name' => $name,
            'email' => $email,
        ]);

        // role and status are not fillable — assigned explicitly (NFR-SEC-07).
        $user->role = UserRole::SuperAdmin;
        $user->status = $isLocal ? UserStatus::Active : UserStatus::PendingActivation;
        $user->password = $isLocal ? Hash::make('password') : Hash::make(Str::password(32));
        $user->email_verified_at = $isLocal ? now() : null;

        $user->save();

        if ($isLocal) {
            $this->say("Super Admin seeded: {$email} / password  (local only)");
        } else {
            $this->say(
                "Super Admin seeded: {$email}. No usable password was set — "
                .'use "Forgot password" to take control of the account.',
            );
        }
    }

    /**
     * Console command, when this seeder was invoked through artisan.
     *
     * Laravel's Seeder::$command is a TYPED, UNINITIALISED property — it is
     * neither set nor null when a seeder runs outside artisan, as it does from
     * the test suite. Reading it directly risks an uninitialised-property
     * error, and isset() on a non-nullable typed property is exactly the kind
     * of ambiguity static analysis should reject.
     *
     * Capturing it into our own properly-nullable property via the documented
     * setCommand() hook removes the ambiguity instead of papering over it.
     */
    private ?Command $consoleCommand = null;

    public function setCommand(Command $command): static
    {
        $this->consoleCommand = $command;

        return parent::setCommand($command);
    }

    /**
     * Report seeder outcome.
     *
     * Written to BOTH the console and the log. The console message is what an
     * operator sees while running the seeder; the log entry is what remains
     * afterwards — and for the production path, "no usable password was set"
     * is information someone may need to find hours later.
     */
    private function say(string $message): void
    {
        $this->consoleCommand?->warn($message);

        Log::info("[SuperAdminSeeder] {$message}");
    }
}
