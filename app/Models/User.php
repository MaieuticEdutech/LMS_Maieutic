<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $certificate_name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $password
 * @property UserRole $role
 * @property UserStatus $status
 * @property string|null $phone
 * @property string|null $avatar_path
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use SoftDeletes;

    /**
     * MASS ASSIGNMENT (NFR-SEC-07, planning.md §9 rule 3).
     *
     * `role` and `status` are DELIBERATELY ABSENT and must never be added.
     * They are privilege fields: if either were fillable, any endpoint doing
     * $user->update($request->validated()) could be turned into a privilege
     * escalation by an attacker adding one field to a form post.
     *
     * They are set explicitly, by name, inside Actions that have already
     * authorised the change. `Model::preventSilentlyDiscardingAttributes()`
     * (AppServiceProvider) makes an attempt to fill them throw rather than
     * silently no-op, so the mistake surfaces immediately.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        // How the learner wants to be named on a certificate. Their own text,
        // deliberately not derived from the parts — see the migration.
        'certificate_name',
        'email',
        'password',
        'phone',
        'avatar_path',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * Normalise the email on write.
     *
     * FR-AUTH-10: A@X.com and a@x.com must resolve to ONE account. Fortify's
     * `lowercase_usernames` handles its own endpoints, but this mutator makes
     * the invariant hold for EVERY write path — seeders, console commands, and
     * the purchase-driven account creation in Phase 12, none of which go
     * through Fortify.
     *
     * Without this, a buyer paying as "Foo@Example.com" would get a second
     * account despite already having "foo@example.com" — exactly the duplicate
     * the customer explicitly asked us to prevent.
     */
    protected function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = mb_strtolower(trim($value));
    }

    /*
    |--------------------------------------------------------------------------
    | Role & status
    |--------------------------------------------------------------------------
    */

    /**
     * THE ONLY ROLE CHECK PERMITTED IN THIS CODEBASE (rule S-7, ADR-005).
     *
     * Never write `$user->role === UserRole::Instructor` at a call site. Every
     * check goes through here, so moving to many-to-many roles in a future
     * version is a change to this one method plus a data migration.
     */
    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    /**
     * @param  list<UserRole>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SuperAdmin);
    }

    public function isInstructor(): bool
    {
        return $this->hasRole(UserRole::Instructor);
    }

    public function isStudent(): bool
    {
        return $this->hasRole(UserRole::Student);
    }

    /*
    |--------------------------------------------------------------------------
    | Names
    |--------------------------------------------------------------------------
    |
    | A person has more than one name in this system and they are not
    | interchangeable:
    |
    |   `name`             the display name. What every email greeting, admin
    |                      table and instructor roster reads. Always present.
    |   first_ / last_name the parts the learner edits. `name` is projected
    |                      from them by UpdateProfile — one writer.
    |   certificate_name   how they want to be named on a credential. Their own
    |                      text, deliberately NOT derived from the parts.
    |
    | Every read below goes through nameField(), for the reason given there.
    |
    */

    /**
     * The name to print on a credential.
     *
     * Falls back to the display name when the learner has stated no preference
     * — which is every account created before the field existed, and every one
     * made by an administrator or (from Phase 12) by a purchase. A certificate
     * bearing a blank line where a name belongs is worse than one bearing the
     * name the system already knows.
     *
     * One accessor, so no caller has to remember the fallback — exactly the
     * kind of rule that gets written correctly once and then wrongly in the
     * second place it is needed.
     */
    public function certificateName(): string
    {
        return $this->nameField('certificate_name') ?? $this->name;
    }

    /**
     * The preference as the learner actually stated it, or null if they have
     * not — with no fallback applied.
     *
     * Distinct from certificateName() because the profile form must show an
     * empty box when there is no preference. Seeding it with the fallback would
     * turn "use my name as above" into an explicit answer the moment anyone
     * opened the page, and it would then stop tracking a later name change.
     */
    public function statedCertificateName(): ?string
    {
        return $this->nameField('certificate_name');
    }

    /**
     * The display name assembled from its parts, or null if neither is set.
     *
     * Used by UpdateProfile to keep `name` in step. Null rather than an empty
     * string so a caller can tell "no parts recorded" from "parts that happen
     * to be blank", and never overwrite a good display name with nothing.
     */
    public function assembledName(): ?string
    {
        $assembled = trim(($this->nameField('first_name') ?? '').' '.($this->nameField('last_name') ?? ''));

        return $assembled !== '' ? $assembled : null;
    }

    /**
     * The name split into the two parts the profile form edits.
     *
     * Uses the stored parts where they exist and splits the display name where
     * they do not — first token as the first name, the remainder as the last.
     *
     * THE SPLIT IS A GUESS, AND ONLY A STARTING POINT. Names do not divide
     * reliably on spaces in any culture, so this seeds the form's two fields
     * rather than deciding anything; whatever the learner corrects it to is
     * what gets stored. Every account created before these columns existed has
     * only `name`, and offering someone two empty boxes when the system already
     * knows their name is a poor way to ask for something it could have filled
     * in.
     *
     * Split on the FIRST space only, and padded rather than using Str::after —
     * which returns the whole string when the delimiter is absent, and would
     * put a mononym like "Prince" in both boxes.
     *
     * @return array{first: string, last: string}
     */
    public function editableNameParts(): array
    {
        [$first, $last] = array_pad(explode(' ', trim($this->name), 2), 2, '');

        return [
            'first' => $this->nameField('first_name') ?? trim($first),
            'last' => $this->nameField('last_name') ?? trim($last),
        ];
    }

    /**
     * One or two letters for the avatar disc.
     *
     * Built from the same parts the profile form edits, so the disc changes
     * when the learner corrects their name rather than staying stuck on
     * whatever registration captured.
     *
     * Falls back to the first letter of the display name, and to "?" for the
     * pathological case of a name that is entirely punctuation — an empty disc
     * looks like a rendering failure, and this is the one place a placeholder
     * is better than nothing.
     */
    public function initials(): string
    {
        $parts = $this->editableNameParts();

        $initials = mb_substr($parts['first'], 0, 1).mb_substr($parts['last'], 0, 1);
        $initials = trim($initials);

        if ($initials === '') {
            $initials = mb_substr(trim($this->name), 0, 1);
        }

        return $initials !== '' ? mb_strtoupper($initials) : '?';
    }

    /**
     * Read one of the optional name columns: trimmed, or null if it holds
     * nothing useful.
     *
     * ═════════════════════════════════════════════════════════════════════
     * READS `$this->attributes` DIRECTLY, ON PURPOSE.
     *
     * `User::create(['name' => …, 'email' => …])` does not re-read the row, so
     * a column the caller never mentioned is ABSENT from the in-memory model
     * rather than null. With `preventAccessingMissingAttributes()` active
     * outside production, `$user->certificate_name` on such an instance throws
     * — so a purchase-created account asking for its certificate name would
     * fail in dev and in CI while passing in production, which is the worst
     * possible arrangement.
     *
     * Reading the model's own attribute bag treats "never selected" and "null"
     * alike, which for these three columns is correct: absent means the learner
     * has stated no preference. `?? null` at each call site would do the same
     * thing by relying on the exception `??` happens to swallow — this says it
     * out loud instead.
     * ═════════════════════════════════════════════════════════════════════
     *
     * Trimming here too, so "no preference" and "   " cannot mean two different
     * things depending on which caller looked.
     */
    private function nameField(string $column): ?string
    {
        $value = trim((string) ($this->attributes[$column] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * May this account authenticate right now?
     *
     * Delegates to the enum so authenticateUsing() and EnsureUserIsActive
     * can never drift apart.
     */
    public function canAuthenticate(): bool
    {
        return $this->status->canAuthenticate();
    }

    /**
     * True for a purchase-created account that has never set a password.
     *
     * Such an account cannot log in: NULL never satisfies Hash::check().
     */
    public function awaitingActivation(): bool
    {
        return $this->password === null
            || $this->status === UserStatus::PendingActivation;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', UserStatus::Active);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeRole(Builder $query, UserRole $role): void
    {
        $query->where('role', $role);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return HasOne<InstructorProfile, $this>
     */
    public function instructorProfile(): HasOne
    {
        return $this->hasOne(InstructorProfile::class);
    }

    /**
     * Audit entries recording actions THIS user performed.
     *
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Overridden so every email uses the LMS branded layout and resolves the
    | organisation identity through BrandingService rather than Laravel's
    | generic templates (FR-MAIL-08, rule S-1).
    |
    */

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
