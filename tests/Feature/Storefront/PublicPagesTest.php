<?php

declare(strict_types=1);

use App\Livewire\Catalogue\Index as CatalogueIndex;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Catalogue\Promotion;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Public pages: landing, catalogue cards, course page (Spotlight × Canvas)
|--------------------------------------------------------------------------
|
| Real data only, and sections with nothing behind them are not rendered.
*/

function publicPagePromotion(?string $until): void
{
    app(SettingsRepository::class)->set(Promotion::SETTING, $until ?? '', 'promotion', 'string', true);
}

beforeEach(function (): void {
    Cache::flush();
    publicPagePromotion(null);
});

it('renders the landing page with no data and hides the empty sections', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Top 10')
        ->assertDontSee('Popular')
        ->assertDontSee('What learners')
        ->assertDontSee('Free for a limited time');
});

it('shows real courses, the Top 10 and the stat strip once there is data', function (): void {
    $course = Course::factory()->published()->create(['title' => 'Landing Showcase Course']);
    Enrollment::factory()->count(2)->create(['course_id' => $course->getKey(), 'enrolled_at' => now()]);
    Cache::flush();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Landing Showcase Course')
        ->assertSee('Top 10')
        ->assertSee('Published courses')
        ->assertSee('Learners enrolled');
});

it('leaves out a figure that has no data behind it', function (): void {
    Course::factory()->published()->create();
    Cache::flush();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Average course rating');
});

it('offers the free launch with a countdown only while the promotion runs', function (): void {
    publicPagePromotion(now()->addDays(10)->format('Y-m-d\TH:i'));

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Free for a limited time')
        ->assertSee('Ends in', false);

    publicPagePromotion(null);

    $this->get(route('home'))->assertOk()->assertDontSee('Free for a limited time');
});

it('shows a New badge and the free price on catalogue cards during the promotion', function (): void {
    Course::factory()->published()->pricedAt(149900)->create(['title' => 'Fresh Card Course', 'published_at' => now()]);
    publicPagePromotion(now()->addDays(3)->format('Y-m-d\TH:i'));

    Livewire::test(CatalogueIndex::class)
        ->assertSee('Fresh Card Course')
        ->assertSee('New')
        ->assertSee('Free')
        ->assertSee('For a limited time');
});

it('shows the real price on catalogue cards when there is no promotion', function (): void {
    Course::factory()->published()->pricedAt(149900)->create(['title' => 'Plain Card Course']);

    Livewire::test(CatalogueIndex::class)
        ->assertSee('Plain Card Course')
        ->assertSee('₹1,499')
        ->assertDontSee('For a limited time');
});

it('sorts the catalogue by popularity', function (): void {
    $quiet = Course::factory()->published()->create(['title' => 'Quiet Course']);
    $busy = Course::factory()->published()->create(['title' => 'Busy Course']);
    Enrollment::factory()->count(3)->create(['course_id' => $busy->getKey()]);
    Enrollment::factory()->count(1)->create(['course_id' => $quiet->getKey()]);

    Livewire::test(CatalogueIndex::class)
        ->set('sort', 'popular')
        ->assertSeeInOrder(['Busy Course', 'Quiet Course']);
});

it('shows the purchase button, not Enrol free, on the course page without a promotion', function (): void {
    $course = Course::factory()->published()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('catalogue.show', $course))
        ->assertOk()
        ->assertSee('Purchase course')
        ->assertSee('Secure checkout via Razorpay.')
        ->assertDontSee('Enrol free')
        ->assertDontSee('Free until');
});

it('shows Enrol free and the Free until countdown on the course page during the promotion', function (): void {
    $course = Course::factory()->published()->create();
    publicPagePromotion(now()->addDays(5)->format('Y-m-d\TH:i'));

    $this->actingAs(User::factory()->create())
        ->get(route('catalogue.show', $course))
        ->assertOk()
        ->assertSee('Enrol free')
        ->assertSee('Free for a limited time')
        ->assertSee('Free until')
        ->assertDontSee('Purchase course');
});
