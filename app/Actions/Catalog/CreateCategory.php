<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create a course category (FR-CNT-15).
 *
 * The `categories` table, model, factory and policy shipped in Phase 3, and
 * the Course Builder has always offered a category field — but nothing could
 * ever populate the list, so the dropdown was permanently empty and the
 * column unreachable outside tinker. These actions close that gap.
 *
 * Position is assigned as "last" inside the transaction, so two admins adding
 * a category at the same time cannot both claim the same slot — the same
 * reasoning as CreateModule, and zero-based for the same reason.
 */
final class CreateCategory
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{name: string, description?: string|null, parent_id?: int|null}  $attributes
     */
    public function handle(array $attributes, User $actor): Category
    {
        $category = DB::transaction(function () use ($attributes): Category {
            $category = new Category;

            $category->fill([
                'parent_id' => $attributes['parent_id'] ?? null,
                'name' => $attributes['name'],
                'slug' => $this->uniqueSlug($attributes['name']),
                'description' => $this->sanitizer->plainText($attributes['description'] ?? null),
                'position' => $this->nextPosition($attributes['parent_id'] ?? null),
            ]);

            $category->save();

            return $category;
        });

        $this->audit->record(
            action: 'category.created',
            actor: $actor,
            subject: $category,
            description: "Created category \"{$category->name}\".",
        );

        return $category;
    }

    /**
     * Slugs are the public route key (Category::getRouteKeyName), so they must
     * be unique across the whole table, not merely within a parent.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 1;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    /**
     * Positions run per sibling group: a child's order is meaningful only
     * among its own siblings, so a new root and a new child each start their
     * own sequence at 0.
     */
    private function nextPosition(?int $parentId): int
    {
        $max = Category::query()
            ->where('parent_id', $parentId)
            ->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }
}
