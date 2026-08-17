<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

/**
 * Who may look at a certificate.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * PUBLIC VERIFICATION IS NOT AUTHORISED HERE, AND THAT IS DELIBERATE.
 *
 * A certificate is only worth anything if a stranger — an employer, a
 * registrar — can check it without an account. That route is public by design
 * and does not consult this policy.
 *
 * What makes it safe is the SHAPE OF THE LOOKUP, not a permission check: the
 * verification page is reachable only by knowing the full random number, shows
 * only what the certificate already asserts in public (a name, a course, a
 * date), and never exposes the holder's email, their other awards, or anything
 * about their account. It is the same trade as a password-reset link.
 *
 * That is precisely why `number` is random rather than sequential and why this
 * model's route key is the number rather than the id — see IssueCertificate.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class CertificatePolicy
{
    /**
     * A super admin sees any certificate; a student sees only their own.
     *
     * Instructors are NOT included. An instructor teaching a course has a
     * legitimate interest in who passed it, which the progress screens already
     * serve — a certificate is a record about the STUDENT, and "taught them
     * once" is not a standing reason to read their credentials afterwards.
     */
    public function view(User $user, Certificate $certificate): bool
    {
        return $user->isSuperAdmin() || $certificate->user_id === $user->getKey();
    }

    /**
     * Listing is always self-scoped — the query filters by the authenticated
     * user, so this only answers "may you have a list at all".
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Nobody, through the web. Certificates are awarded by IssueCertificate off
     * the back of a real completion, never created by hand — the same rule as
     * enrollments, and for the same reason: a credential anyone can mint is not
     * a credential.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Certificate $certificate): bool
    {
        return false;
    }

    public function delete(User $user, Certificate $certificate): bool
    {
        return false;
    }
}
