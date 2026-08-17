<?php

declare(strict_types=1);

use App\Actions\Catalogue\SubmitCourseReview;
use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Livewire\Catalogue\Index as CatalogueIndex;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\User;
use App\Services\Enrollment\EnrollmentAccessService;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Course ratings (design handoff §2 — the "★ 4.8" and the RATING facet)
|--------------------------------------------------------------------------
|
| The star appears on a page whose job is to sell a course, so the number behind
| it has to be exactly right. Three things carry that:
|
|   only someone with ACCESS may rate, decided through EnrollmentAccessService
|   rather than a second copy of the rule;
|
|   the average is derived from an integer SUM and COUNT, never stored as a
|   float, and SubmitCourseReview is the only writer of either;
|
|   an unrated course is NULL, not 0.0 — "no ratings yet" and "rated zero" are
|   different claims and the second one is a lie.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();
    $this->course = Course::factory()->published()->create();

    $this->enrol = fn (User $u, Course $c) => app(GrantEnrollment::class)
        ->handle($u, $c, EnrollmentSource::AdminGrant, $this->admin);

    $this->enrollment = ($this->enrol)($this->student, $this->course);

    $this->submit = fn (int $rating, ?string $body = null) => app(SubmitCourseReview::class)
        ->handle($this->enrollment->refresh(), $rating, $body);
});

/*
| ═══════════════ WHO MAY RATE ═══════════════
*/
it('accepts a rating from an enrolled student', function (): void {
    $review = ($this->submit)(5, 'Genuinely changed how I read data.');

    expect($review->rating)->toBe(5)
        ->and($review->body)->toBe('Genuinely changed how I read data.');
});

it('refuses a rating once access has ended', function (): void {
    $this->enrollment->forceFill(['status' => EnrollmentStatus::Refunded])->save();
    app(EnrollmentAccessService::class)->flush();

    // Decided through EnrollmentAccessService, not by reading the status here —
    // one definition of "has access" (rule S-8).
    expect(fn () => ($this->submit)(5))->toThrow(RuntimeException::class);
});

it('refuses a rating outside 1 to 5', function (int $rating): void {
    expect(fn () => ($this->submit)($rating))->toThrow(InvalidArgumentException::class);
})->with([0, 6, -1, 99]);

it('refuses an out-of-range rating at the database too', function (): void {
    // The Action rejects it first, but the CHECK constraint is what makes the
    // rule true for every writer that ever exists (ADR-012).
    expect(fn () => CourseReview::factory()->create([
        'enrollment_id' => $this->enrollment->getKey(),
        'user_id' => $this->student->getKey(),
        'course_id' => $this->course->getKey(),
        'rating' => 9,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

/*
| ═══════════════ ONE REVIEW PER ENROLMENT ═══════════════
*/
it('updates the existing review rather than adding a second', function (): void {
    ($this->submit)(3, 'Decent.');
    ($this->submit)(5, 'Better than I first thought.');

    expect(CourseReview::query()->count())->toBe(1)
        ->and(CourseReview::query()->firstOrFail()->rating)->toBe(5);
});

it('keeps one review per enrolment at the database level', function (): void {
    ($this->submit)(4);

    // The Action's read-then-write cannot stop two tabs racing. The unique
    // index can.
    expect(fn () => CourseReview::factory()->create([
        'enrollment_id' => $this->enrollment->getKey(),
        'user_id' => $this->student->getKey(),
        'course_id' => $this->course->getKey(),
    ]))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

/*
| ═══════════════ THE CACHED COUNTERS ═══════════════
*/
it('moves the counters when a review lands', function (): void {
    ($this->submit)(4);

    $course = $this->course->refresh();

    expect($course->rating_sum)->toBe(4)
        ->and($course->rating_count)->toBe(1)
        ->and($course->averageRating())->toBe(4.0);
});

it('moves the sum but not the count when a review is edited', function (): void {
    ($this->submit)(2);
    ($this->submit)(5);

    $course = $this->course->refresh();

    // The delta, not the new value. Adding 5 to a sum that already held 2 would
    // give an average of 7 out of one review.
    expect($course->rating_sum)->toBe(5)
        ->and($course->rating_count)->toBe(1)
        ->and($course->averageRating())->toBe(5.0);
});

it('averages several students correctly', function (): void {
    ($this->submit)(5);

    foreach ([4, 3] as $rating) {
        $enrollment = ($this->enrol)(User::factory()->create(), $this->course);
        app(SubmitCourseReview::class)->handle($enrollment, $rating);
    }

    $course = $this->course->refresh();

    expect($course->rating_sum)->toBe(12)
        ->and($course->rating_count)->toBe(3)
        ->and($course->averageRating())->toBe(4.0);
});

it('rounds the average to one decimal place', function (): void {
    // 5 + 4 = 9 over 2 = 4.5; add a 4 and it is 13/3 = 4.333…
    ($this->submit)(5);

    foreach ([4, 4] as $rating) {
        $enrollment = ($this->enrol)(User::factory()->create(), $this->course);
        app(SubmitCourseReview::class)->handle($enrollment, $rating);
    }

    expect($this->course->refresh()->averageRating())->toBe(4.3);
});

it('stores no average column at all, only integers', function (): void {
    /*
     * Asserted against the SCHEMA rather than against a PHP value, because the
     * cast would make `toBeInt()` pass whatever the column actually held.
     *
     * The whole reason there is no `rating_average`: a float drifts as it is
     * recomputed, and two courses that received identical ratings could end up
     * displaying differently. Sum and count are exact and the mean is derived
     * on read — the same trick as Money keeping paise.
     */
    // 'int4' is PostgreSQL's own name for a 4-byte integer. Asserted as the
    // driver reports it rather than translated, because the point is what the
    // DATABASE holds — a test that normalised the name could pass against a
    // numeric column on another driver.
    expect(Schema::hasColumn('courses', 'rating_average'))->toBeFalse()
        ->and(Schema::getColumnType('courses', 'rating_sum'))->toBe('int4')
        ->and(Schema::getColumnType('courses', 'rating_count'))->toBe('int4');
});

/*
| ═══════════════ UNRATED IS NOT ZERO ═══════════════
*/
it('reports no average at all for a course nobody has rated', function (): void {
    // NULL, not 0.0. A card showing ★ 0.0 on a brand-new course states a bad
    // review it never received.
    expect($this->course->averageRating())->toBeNull()
        ->and($this->course->hasRatings())->toBeFalse();
});

it('never prints a star for an unrated course', function (): void {
    $this->get(route('catalogue.show', $this->course))
        ->assertOk()
        ->assertDontSee('★');
});

it('prints the star once the course has been rated', function (): void {
    ($this->submit)(5);

    $this->get(route('catalogue.show', $this->course->refresh()))
        ->assertOk()
        ->assertSee('★ 5.0');
});

/*
| ═══════════════ THE RATING FACET ═══════════════
*/
it('narrows the catalogue by minimum rating', function (): void {
    ($this->submit)(5);

    $poor = Course::factory()->published()->create(['title' => 'Barely Rated Course']);
    app(SubmitCourseReview::class)->handle(($this->enrol)(User::factory()->create(), $poor), 3);

    Livewire::test(CatalogueIndex::class)
        ->set('rating', ['4.5'])
        ->assertSee($this->course->title)
        ->assertDontSee('Barely Rated Course');
});

it('excludes an unrated course from every rating band', function (): void {
    // Not "4.5 and up" — unrated. A sum of 0 over a count of 0 must not pass a
    // threshold test by accident.
    Course::factory()->published()->create(['title' => 'Nobody Rated This']);

    Livewire::test(CatalogueIndex::class)
        ->set('rating', ['3.0'])
        ->assertDontSee('Nobody Rated This');
});

it('ignores a rating band that does not exist', function (): void {
    ($this->submit)(5);

    Livewire::test(CatalogueIndex::class)
        ->set('rating', ['9.9'])
        ->assertSee($this->course->title);
});

it('takes the lowest band when several are ticked', function (): void {
    // "4.5 & up" and "3.0 & up" together can only sensibly mean "3.0 and up" —
    // the bands nest rather than sitting side by side.
    ($this->submit)(5);

    $middling = Course::factory()->published()->create(['title' => 'Middling Course']);
    app(SubmitCourseReview::class)->handle(($this->enrol)(User::factory()->create(), $middling), 3);

    Livewire::test(CatalogueIndex::class)
        ->set('rating', ['4.5', '3.0'])
        ->assertSee($this->course->title)
        ->assertSee('Middling Course');
});

/*
| ═══════════════ THE WORDS ═══════════════
*/
it('shows written reviews on the course page', function (): void {
    ($this->submit)(5, 'The section on missing values alone was worth it.');

    $this->get(route('catalogue.show', $this->course->refresh()))
        ->assertOk()
        ->assertSee('What learners say')
        ->assertSee('The section on missing values alone was worth it.');
});

it('does not list a rating that came with no words', function (): void {
    // A star with no prose still counts toward the average — it just has
    // nothing to display in a quote list.
    ($this->submit)(4);

    $this->get(route('catalogue.show', $this->course->refresh()))
        ->assertOk()
        ->assertDontSee('What learners say');

    expect($this->course->refresh()->rating_count)->toBe(1);
});

it('stores a blank body as null rather than an empty string', function (): void {
    expect(($this->submit)(4, '   ')->body)->toBeNull();
});
