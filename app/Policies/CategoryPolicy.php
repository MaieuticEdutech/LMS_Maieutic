<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Authorisation for catalogue categories (FR-CRS-07).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * PUBLIC TO READ, SUPER-ADMIN TO CHANGE — and the asymmetry is the point.
 *
 * A category is the one piece of catalogue structure that is genuinely public:
 * the catalogue index groups by it and a guest browsing courses sees it. So
 * `viewAny`/`view` do not gate on a user at all.
 *
 * Changing one is a different matter. Renaming or re-parenting a category
 * moves every course underneath it and changes public URLs, which is an
 * administrative act rather than an editorial one — instructors, who may edit
 * their own course content, do not get to reorganise the shop window.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * This policy was missing until the Phase 1–11 completeness pass. Nothing was
 * broken by its absence — no screen manages categories yet — but an
 * unregistered policy DENIES SILENTLY, so the first screen that did would have
 * failed with no error anywhere. PolicyCoverageTest now asserts the wiring.
 */
final class CategoryPolicy
{
    public function viewAny(?User $actor): bool
    {
        return true;
    }

    public function view(?User $actor, Category $category): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, Category $category): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(User $actor, Category $category): bool
    {
        return $actor->isSuperAdmin();
    }
}
