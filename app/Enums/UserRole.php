<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The three fixed roles. One per user in V1 (PD-02, ADR-005).
 *
 * NO PERMISSION PACKAGE. Three roles with fixed capabilities do not need a
 * runtime permission engine; policies express the rules more precisely and
 * with zero dependency.
 *
 * THE MIGRATION PATH MATTERS MORE THAN THE CURRENT SHAPE:
 * `$user->hasRole(UserRole::X)` is the ONLY role call-site pattern permitted
 * anywhere in this codebase (planning.md rule S-7). Never write
 * `$user->role === 'instructor'`. Because every check funnels through that one
 * method, introducing a many-to-many `role_user` pivot later means changing
 * one method plus a data migration — no policy, middleware or view changes.
 *
 * Backed values are the strings stored in `users.role` and enforced by a
 * database CHECK constraint (ADR-012).
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Instructor = 'instructor';
    case Student = 'student';

    /**
     * Human-readable label for UI and audit descriptions.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Instructor => 'Instructor',
            self::Student => 'Student',
        };
    }

    /**
     * Where this role lands after logging in (architecture.md §7.3).
     *
     * Returned as a path rather than a route name so it stays resolvable
     * before the later phases register their named routes.
     */
    public function homePath(): string
    {
        return match ($this) {
            self::SuperAdmin => '/admin',
            self::Instructor => '/instructor',
            self::Student => '/dashboard',
        };
    }

    /**
     * Roles that may be created through the administrator UI.
     *
     * SuperAdmin is ABSENT deliberately. FR-RBAC-07: the Super Admin role must
     * not be assignable through any self-service path. The first one is seeded
     * at deployment; further ones require a deliberate, audited action rather
     * than a dropdown option.
     *
     * @return list<self>
     */
    public static function assignableByAdmin(): array
    {
        return [self::Instructor, self::Student];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
