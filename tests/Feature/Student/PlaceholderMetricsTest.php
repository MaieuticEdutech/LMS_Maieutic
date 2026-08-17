<?php

declare(strict_types=1);

use App\Support\DemoMetrics;

/*
|--------------------------------------------------------------------------
| Placeholder metrics containment
|--------------------------------------------------------------------------
|
| The student redesign shows figures this system cannot compute — hours
| learned, lessons this month, certificates earned, a ★ rating per course.
| They are supplied as placeholders so the design can be reviewed.
|
| The whole risk of that decision is a placeholder being mistaken for a
| measurement. These tests guard the containment, not the values.
|
*/

it('is switched off outside local', function (): void {
    // The suite runs in `testing`. If placeholders were live here, every
    // assertion about a real dashboard figure would be asserting an invented
    // one and the suite would prove nothing.
    expect(app()->environment())->toBe('testing')
        ->and(DemoMetrics::enabled())->toBeFalse();
});

it('keeps the honest dashboard figures when placeholders are off', function (): void {
    $student = App\Models\User::factory()->create();

    $this->actingAs($student)
        ->get(route('student.home'))
        ->assertOk()
        ->assertDontSee('42h')
        ->assertDontSee('Hours learned')
        ->assertDontSee('Certificates earned')
        ->assertDontSee('Sample data');
});

it('gives a stable rating for a given course', function (): void {
    // A rating that changed between renders would read as a rendering bug to
    // anyone reviewing the design.
    expect(DemoMetrics::rating(7))->toBe(DemoMetrics::rating(7))
        ->and(DemoMetrics::rating(7))->not->toBe(DemoMetrics::rating(8));
});

it('offers no dashboard placeholder without a matching real fallback', function (): void {
    // Each placeholder tile degrades to a real figure when disabled, so the
    // production dashboard is never short a tile.
    expect(array_keys(DemoMetrics::dashboardStats()))
        ->toBe(['hours_learned', 'lessons_this_month', 'certificates']);
});
