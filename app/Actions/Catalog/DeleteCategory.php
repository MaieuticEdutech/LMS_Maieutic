<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Delete a category (FR-CNT-15).
 *
 * NOT BLOCKED WHEN IN USE, DELIBERATELY — the schema already decided this.
 * Both `categories.parent_id` and `courses.category_id` are nullOnDelete, so
 * the database promotes any children to roots and leaves their courses
 * uncategorised. A category is a browsing aid, not an ownership record;
 * refusing here would contradict the migration and leave an admin unable to
 * tidy a taxonomy without first re-filing every course.
 *
 * What that costs is surprise, so the counts are captured BEFORE the delete
 * and written into the audit entry. The screen shows the same numbers on the
 * confirmation, so the effect is consented to rather than discovered.
 *
 * Hard delete, not soft: a category carries no financial or access record,
 * which is what SoftDeletes exists to protect on Course.
 */
final class DeleteCategory
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Category $category, User $actor): void
    {
        $name = $category->name;
        $courseCount = $category->courses()->count();
        $childCount = $category->children()->count();

        DB::transaction(static function () use ($category): void {
            $category->delete();
        });

        $this->audit->record(
            action: 'category.deleted',
            actor: $actor,
            subject: $category,
            description: sprintf(
                'Deleted category "%s". %d course(s) left uncategorised, %d subcategory(ies) promoted to top level.',
                $name,
                $courseCount,
                $childCount,
            ),
        );
    }
}
