<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Course;
use App\Support\CourseArt;
use Illuminate\Support\Facades\Blade;

/*
|--------------------------------------------------------------------------
| Storefront components (Spotlight × Canvas)
|--------------------------------------------------------------------------
|
| The pieces every redesigned page is built from. These tests pin the rules
| that would be easy to break quietly: a free price still shows the real
| price struck through, no rating renders as nothing rather than 0.0, and the
| course art is chosen from the category without leaking course data into
| raw SVG.
*/

/**
 * @param  array<string, mixed>  $data
 */
function renderBlade(string $template, array $data = []): string
{
    return Blade::render($template, $data);
}

it('shows the real price when no promotion is running', function (): void {
    $course = Course::factory()->pricedAt(149900)->create();

    $html = renderBlade('<x-store.price :course="$course" />', ['course' => $course]);

    expect($html)->toContain('₹1,499')
        ->not->toContain('Free');
});

it('shows Free with the real price struck through during the promotion', function (): void {
    $course = Course::factory()->pricedAt(149900)->create();

    $html = renderBlade('<x-store.price :course="$course" :free="true" />', ['course' => $course]);

    expect($html)->toContain('Free')
        ->toContain('<s')
        ->toContain('₹1,499')
        ->toContain('For a limited time');
});

it('keeps paise when a price is not a whole number of rupees', function (): void {
    $course = Course::factory()->pricedAt(149950)->create();

    expect(renderBlade('<x-store.price :course="$course" />', ['course' => $course]))->toContain('₹1,499.50');
});

it('renders no rating at all for a course nobody has rated', function (): void {
    $course = Course::factory()->create(['rating_sum' => 0, 'rating_count' => 0]);

    expect(trim(renderBlade('<x-store.rating :course="$course" />', ['course' => $course])))->toBe('');
});

it('renders the mean and count for a rated course', function (): void {
    $course = Course::factory()->create(['rating_sum' => 48, 'rating_count' => 10]);

    expect(renderBlade('<x-store.rating :course="$course" />', ['course' => $course]))
        ->toContain('4.8')
        ->toContain('(10)')
        ->toContain('Rated 4.8 out of 5 from 10 reviews');
});

it('draws initials when there is no photo, and the photo when there is', function (): void {
    expect(renderBlade('<x-store.avatar name="Karthik Rao" />'))->toContain('KR')->not->toContain('<img');

    expect(renderBlade('<x-store.avatar name="Karthik Rao" photo="/storage/instructors/k.jpg" />'))
        ->toContain('<img')
        ->toContain('/storage/instructors/k.jpg');
});

it('puts the course title, link and price on the card', function (): void {
    $course = Course::factory()->published()->pricedAt(99900)->create(['title' => 'SQL for Analysts']);

    $html = renderBlade('<x-store.course-card :course="$course" href="/courses/sql" badge="New" />', ['course' => $course]);

    expect($html)->toContain('SQL for Analysts')
        ->toContain('href="/courses/sql"')
        ->toContain('New')
        ->toContain('₹999');
});

it('picks the course art from the category', function (string $category, string $kind): void {
    $course = Course::factory()->inCategory(Category::factory()->create(['name' => $category]))->create(['title' => 'A course']);

    expect(CourseArt::kind($course->load('category')))->toBe($kind);
})->with([
    ['Cyber Security', 'shield'],
    ['Data Science', 'chart'],
    ['Cloud Computing', 'cloud'],
    ['UI Design', 'design'],
    ['Web Development', 'code'],
]);

it('never puts course data into the raw SVG', function (): void {
    $course = Course::factory()->create(['title' => '<script>alert(1)</script> Laravel']);

    expect(CourseArt::svg($course))->not->toContain('<script>')->not->toContain('alert(1)');
});

it('gives the same course the same colours every time', function (): void {
    $course = Course::factory()->create();

    expect(CourseArt::palette($course))->toBe(CourseArt::palette(Course::query()->whereKey($course->getKey())->firstOrFail()));
});
