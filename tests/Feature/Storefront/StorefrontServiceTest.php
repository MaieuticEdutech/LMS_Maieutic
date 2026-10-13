<?php

declare(strict_types=1);

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Catalogue\StorefrontService;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| StorefrontService: Top 10, badges and stats
|--------------------------------------------------------------------------
|
| The numbers on the storefront are real counts. These tests pin the windows
| and the ordering, so "Top 10 this week" can't quietly turn into "all time".
*/

beforeEach(function (): void {
    Cache::flush();
    $this->store = app(StorefrontService::class);
});

function enrolMany(Course $course, int $count, ?DateTimeInterface $at = null): void
{
    Enrollment::factory()->count($count)->create([
        'course_id' => $course->getKey(),
        'enrolled_at' => $at ?? now(),
    ]);
}

it('ranks published courses by enrolments in the last 7 days', function (): void {
    $popular = Course::factory()->published()->create();
    $quiet = Course::factory()->published()->create();
    enrolMany($popular, 3);
    enrolMany($quiet, 1);

    expect($this->store->topThisWeek()->pluck('id')->all())->toBe([$popular->getKey(), $quiet->getKey()]);
});

it('ignores enrolments older than 7 days', function (): void {
    $old = Course::factory()->published()->create();
    enrolMany($old, 5, now()->subDays(8));

    expect($this->store->topThisWeek())->toBeEmpty();
});

it('never ranks a course that is not published', function (): void {
    $draft = Course::factory()->create();
    enrolMany($draft, 4);

    expect($this->store->topThisWeek())->toBeEmpty();
});

it('respects the limit', function (): void {
    foreach (range(1, 4) as $i) {
        enrolMany(Course::factory()->published()->create(), $i);
    }

    expect($this->store->topThisWeek(2))->toHaveCount(2);
});

it('labels the most-enrolled recent courses Bestseller, and only three of them', function (): void {
    $least = Course::factory()->published()->create(['published_at' => now()->subYear()]);
    $most = Course::factory()->published()->create(['published_at' => now()->subYear()]);
    enrolMany($least, 1);
    enrolMany(Course::factory()->published()->create(['published_at' => now()->subYear()]), 2);
    enrolMany(Course::factory()->published()->create(['published_at' => now()->subYear()]), 3);
    enrolMany($most, 4);

    expect($this->store->bestsellerIds())->toHaveCount(3)
        ->and($this->store->badgeFor($most))->toBe('Bestseller')
        ->and($this->store->badgeFor($least))->toBeNull();
});

it('labels a recently published course New, and an older one nothing', function (): void {
    $fresh = Course::factory()->published()->create(['published_at' => now()->subDays(5)]);
    $old = Course::factory()->published()->create(['published_at' => now()->subDays(60)]);

    expect($this->store->badgeFor($fresh, []))->toBe('New')
        ->and($this->store->badgeFor($old, []))->toBeNull();
});

it('lets Bestseller win over New', function (): void {
    $course = Course::factory()->published()->create(['published_at' => now()->subDay()]);

    expect($this->store->badgeFor($course, [(int) $course->getKey()]))->toBe('Bestseller');
});

it('sums an instructor\'s courses, learners and a review-weighted rating', function (): void {
    $instructor = User::factory()->instructor()->create();
    $big = Course::factory()->published()->create(['rating_sum' => 450, 'rating_count' => 100]);
    $small = Course::factory()->published()->create(['rating_sum' => 5, 'rating_count' => 1]);
    $draft = Course::factory()->create(['rating_sum' => 50, 'rating_count' => 10]);
    foreach ([$big, $small, $draft] as $course) {
        $course->instructors()->attach($instructor, ['role_in_course' => 'lead', 'assigned_at' => now()]);
    }
    enrolMany($big, 2);
    enrolMany($small, 1);

    $stats = $this->store->instructorStats($instructor);

    // (450 + 5) / (100 + 1) = 4.5; the draft course doesn't count.
    expect($stats)->toBe(['courses' => 2, 'learners' => 3, 'rating' => 4.5, 'reviews' => 101]);
});

it('reports no instructor rating rather than zero when nothing is rated', function (): void {
    $instructor = User::factory()->instructor()->create();

    expect($this->store->instructorStats($instructor)['rating'])->toBeNull();
});

it('counts platform totals from real rows', function (): void {
    Course::factory()->count(2)->published()->create(['rating_sum' => 9, 'rating_count' => 2]);
    Course::factory()->create();
    Enrollment::factory()->count(3)->create();
    Certificate::factory()->create();

    $stats = $this->store->platformStats();

    expect($stats['courses'])->toBe(2)
        ->and($stats['learners'])->toBeGreaterThanOrEqual(3)
        ->and($stats['certificates'])->toBe(1)
        ->and($stats['rating'])->toBe(4.5);
});
