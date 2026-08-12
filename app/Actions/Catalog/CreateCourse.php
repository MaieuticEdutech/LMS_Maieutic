<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create a course (FR-CRS-01, FR-CRS-02).
 *
 * Always created as a DRAFT. Publishing is a separate, validated transition
 * (PublishCourse) because a course with no modules and no price is not
 * something a student should ever be able to reach.
 *
 * `price_amount` is set explicitly here rather than mass-assigned: it is
 * money, and the database enforces `> 0` (ADR-014, all V1 courses are paid).
 */
final class CreateCourse
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{
     *     title: string,
     *     price_amount: int,
     *     subtitle?: string|null,
     *     description?: string|null,
     *     category_id?: int|null,
     *     level?: CourseLevel|string|null,
     *     language?: string|null,
     *     outcomes?: list<string>|null,
     *     requirements?: list<string>|null,
     *     currency?: string|null,
     *     requires_final_test?: bool,
     * }  $attributes
     */
    public function handle(array $attributes, User $actor): Course
    {
        $course = DB::transaction(function () use ($attributes, $actor): Course {
            $course = new Course;

            $course->fill([
                'title' => $attributes['title'],
                'slug' => $this->uniqueSlug($attributes['title']),
                'subtitle' => $this->sanitizer->plainText($attributes['subtitle'] ?? null),
                // Rich text is sanitised against an allow-list ON SAVE
                // (NFR-SEC-06) — never trusted to be escaped at render time.
                'description' => $this->sanitizer->sanitize($attributes['description'] ?? null),
                'category_id' => $attributes['category_id'] ?? null,
                'level' => $attributes['level'] ?? CourseLevel::Beginner,
                'language' => $attributes['language'] ?? 'en',
                'outcomes' => $this->cleanList($attributes['outcomes'] ?? null),
                'requirements' => $this->cleanList($attributes['requirements'] ?? null),
                'currency' => $attributes['currency'] ?? config()->string('lms.payments.currency', 'INR'),
                'requires_final_test' => $attributes['requires_final_test'] ?? false,
            ]);

            // Not fillable — money and ownership are assigned by name
            // (NFR-SEC-07).
            $course->price_amount = $attributes['price_amount'];
            $course->status = CourseStatus::Draft;
            $course->created_by = $actor->getKey();

            $course->save();

            return $course;
        });

        $this->audit->record(
            action: 'course.created',
            actor: $actor,
            subject: $course,
            description: "Created course \"{$course->title}\".",
        );

        return $course;
    }

    /**
     * Slug uniqueness is enforced by the database; this avoids the collision
     * rather than catching the exception.
     */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'course';
        $slug = $base;
        $suffix = 1;

        while (Course::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    /**
     * Outcomes and requirements are plain strings shown as bullet lists —
     * strip any markup rather than storing it.
     *
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
