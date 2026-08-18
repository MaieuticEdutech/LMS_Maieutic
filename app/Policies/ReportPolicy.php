<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Who may open a report at all (FR-RPT-07).
 *
 * COARSE BY DESIGN. This answers "may this kind of actor be on a reporting
 * screen"; WHICH rows they then see is ReportScope's job, applied inside every
 * query service. Splitting it that way is deliberate — a policy that returned
 * true and left scoping to the caller would put the security decision at each
 * of five call sites instead of one.
 *
 * There is deliberately no `viewRevenue` allowance for instructors, and no
 * instructor route to the revenue report. An absent screen cannot leak;
 * a shared screen behind a boolean eventually will (FR-INS-10).
 */
final class ReportPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->isInstructor();
    }

    /**
     * Enrollment, course progress, assessment and student reports — all
     * scoped to assigned courses for an instructor.
     */
    public function viewOperational(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    /**
     * Revenue. Super admin only, forever (FR-RPT-07, FR-INS-10).
     */
    public function viewFinancial(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }
}
