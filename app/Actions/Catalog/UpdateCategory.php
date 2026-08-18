<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Edit a category (FR-CNT-15).
 *
 * THE SLUG IS NOT REGENERATED FROM A RENAMED CATEGORY. It is the public route
 * key, so rewriting it on every rename would silently break links that are
 * already in the wild — the same reason a course keeps its slug through a
 * retitle.
 */
final class UpdateCategory
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{name?: string, description?: string|null, parent_id?: int|null}  $attributes
     */
    public function handle(Category $category, array $attributes, User $actor): Category
    {
        if (array_key_exists('parent_id', $attributes)) {
            $this->assertParentIsLegal($category, $attributes['parent_id']);
        }

        DB::transaction(function () use ($category, $attributes): void {
            if (array_key_exists('name', $attributes)) {
                $category->name = $attributes['name'];
            }

            if (array_key_exists('description', $attributes)) {
                $category->description = $this->sanitizer->plainText($attributes['description']);
            }

            if (array_key_exists('parent_id', $attributes)) {
                $category->parent_id = $attributes['parent_id'];
            }

            $category->save();
        });

        $this->audit->record(
            action: 'category.updated',
            actor: $actor,
            subject: $category,
            description: "Updated category \"{$category->name}\".",
        );

        return $category->refresh();
    }

    /**
     * A category may not be its own parent, nor a descendant of itself.
     *
     * Without this a two-node cycle is one dropdown selection away, and every
     * subsequent tree walk — the picker, the public catalogue, any breadcrumb
     * — recurses until it exhausts memory. The tree is shallow, so walking up
     * from the proposed parent is cheap and needs no recursive CTE.
     */
    private function assertParentIsLegal(Category $category, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $category->getKey()) {
            throw new InvalidArgumentException('A category cannot be its own parent.');
        }

        $ancestor = Category::query()->find($parentId);

        while ($ancestor !== null) {
            if ($ancestor->getKey() === $category->getKey()) {
                throw new InvalidArgumentException(
                    'That would put the category inside one of its own subcategories.',
                );
            }

            $ancestor = $ancestor->parent_id === null
                ? null
                : Category::query()->find($ancestor->parent_id);
        }
    }
}
