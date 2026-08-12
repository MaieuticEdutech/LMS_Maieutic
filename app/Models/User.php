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
