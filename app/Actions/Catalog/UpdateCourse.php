<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\Course;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Support\Facades\DB;

/**
 * Update a course's metadata (FR-CRS-01).
 *
 * NOT handled here, deliberately:
 *   - `status` — publishing is a validated transition (PublishCourse)
 *   - `created_by` — ownership never changes
 *
 * `price_amount` IS updatable, but note FR-CRS-11: changing a price must
 * never alter the recorded amount of an existing order. That holds because
 * orders store their own `amount_total` at purchase time rather than reading
 * the course's current price — a property of Track C's schema, not something
 * this action needs to enforce.
 */
final class UpdateCourse
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Course $course, array $attributes, User $actor): Course
    {
        $before = $course->only(['title', 'subtitle', 'price_amount', 'category_id', 'level', 'language']);

        DB::transaction(function () use ($course, $attributes): void {
            $fillable = [];

            foreach (['title', 'category_id', 'level', 'language', 'currency', 'requires_final_test'] as $key) {
                if (array_key_exists($key, $attributes)) {
                    $fillable[$key] = $attributes[$key];
                }
            }

            if (array_key_exists('subtitle', $attributes)) {
                $fillable['subtitle'] = $this->sanitizer->plainText(
                    $attributes['subtitle'] === null ? null : (string) $attributes['subtitle'],
                );
            }

            if (array_key_exists('description', $attributes)) {
                $fillable['description'] = $this->sanitizer->sanitize(
                    $attributes['description'] === null ? null : (string) $attributes['description'],
                );
            }

            foreach (['outcomes', 'requirements'] as $key) {
                if (array_key_exists($key, $attributes)) {
                    /** @var list<string>|null $list */
                    $list = $attributes[$key];
                    $fillable[$key] = $this->cleanList($list);
                }
            }

            $course->fill($fillable);

            // Money is not fillable — assigned by name (NFR-SEC-07).
            if (array_key_exists('price_amount', $attributes)) {
                $course->price_amount = (int) $attributes['price_amount'];
            }

            $course->save();
        });

        $course->refresh();

        $this->audit->record(
            action: 'course.updated',
            actor: $actor,
            subject: $course,
            changes: [
                'before' => $before,
                'after' => $course->only(array_keys($before)),
            ],
            description: "Updated course \"{$course->title}\".",
        );

        return $course;
    }

    /**
     * @param  list<string>|null  $values
     * @return list<string>|null
     */
    private function cleanList(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $clean = array_values(array_filter(array_map(
            fn (string $value): string => (string) $this->sanitizer->plainText($value),
            $values,
        ), static fn (string $value): bool => $value !== ''));

        return $clean === [] ? null : $clean;
    }
}
