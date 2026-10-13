<?php

declare(strict_types=1);

use App\Actions\Enrollment\EnrolInFreePromotion;
use App\Enums\EnrollmentSource;
use App\Events\EnrollmentGranted;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Services\Catalogue\Promotion;
use App\Services\Settings\SettingsRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| Free-launch promotion (planning.md §16.4)
|--------------------------------------------------------------------------
|
| A third audited path to an enrollment. It must route through GrantEnrollment,
| must do nothing outside the promotion window, and must leave the paid path
| (checkout, price) intact and merely hidden.
*/

function setPromotion(?string $until): void
{
    app(SettingsRepository::class)->set(Promotion::SETTING, $until ?? '', 'promotion', 'string', true);
}

beforeEach(function (): void {
    $this->student = User::factory()->create();
    $this->course = Course::factory()->published()->create();
});

it('is inactive when no end date is set', function (): void {
    setPromotion(null);

    expect(app(Promotion::class)->active())->toBeFalse();
});

it('is active until the end date and inactive after it', function (): void {
    setPromotion(now()->addDay()->format('Y-m-d\TH:i'));
    expect(app(Promotion::class)->active())->toBeTrue();

    setPromotion(now()->subDay()->format('Y-m-d\TH:i'));
    expect(app(Promotion::class)->active())->toBeFalse();
});

it('treats a bare date as running through the end of that day, in IST', function (): void {
    setPromotion(now('Asia/Kolkata')->format('Y-m-d'));

    expect(app(Promotion::class)->active())->toBeTrue();
});

it('fails closed on a value it cannot read', function (): void {
    setPromotion('not a date');

    expect(app(Promotion::class)->active())->toBeFalse();
});

it('enrols a student through GrantEnrollment with the free_promotion source and an audit row', function (): void {
    Event::fake([EnrollmentGranted::class]);
    setPromotion(now()->addWeek()->format('Y-m-d\TH:i'));

    $this->actingAs($this->student)
        ->post(route('catalogue.enrol-free', $this->course))
        ->assertRedirect(route('student.courses.play', $this->course));

    $enrollment = Enrollment::query()->where('user_id', $this->student->getKey())->where('course_id', $this->course->getKey())->firstOrFail();

    expect($enrollment->source)->toBe(EnrollmentSource::FreePromotion)
        ->and($enrollment->granted_by)->toBe($this->student->getKey())
        ->and($enrollment->expires_at)->toBeNull();

    Event::assertDispatched(EnrollmentGranted::class);
    expect(AuditLog::query()->where('action', 'enrollment.granted')->where('user_id', $this->student->getKey())->exists())->toBeTrue();
});

it('is idempotent: a second click makes no second enrollment and no second event', function (): void {
    Event::fake([EnrollmentGranted::class]);
    setPromotion(now()->addWeek()->format('Y-m-d\TH:i'));

    $this->actingAs($this->student)->post(route('catalogue.enrol-free', $this->course));
    $this->actingAs($this->student)->post(route('catalogue.enrol-free', $this->course))
        ->assertRedirect(route('student.courses.play', $this->course));

    expect(Enrollment::query()->where('user_id', $this->student->getKey())->count())->toBe(1);
    Event::assertDispatchedTimes(EnrollmentGranted::class, 1);
});

it('refuses when the promotion is not running', function (): void {
    setPromotion(null);

    $this->actingAs($this->student)
        ->post(route('catalogue.enrol-free', $this->course))
        ->assertForbidden();

    expect(Enrollment::query()->count())->toBe(0);
});

it('refuses an unpublished course', function (): void {
    setPromotion(now()->addWeek()->format('Y-m-d\TH:i'));
    $draft = Course::factory()->create();

    $this->actingAs($this->student)
        ->post(route('catalogue.enrol-free', $draft))
        ->assertForbidden();

    expect(Enrollment::query()->count())->toBe(0);
});

it('sends a guest to sign in rather than enrolling', function (): void {
    setPromotion(now()->addWeek()->format('Y-m-d\TH:i'));

    $this->post(route('catalogue.enrol-free', $this->course))->assertRedirect(route('login'));

    expect(Enrollment::query()->count())->toBe(0);
});

it('refuses an instructor', function (): void {
    setPromotion(now()->addWeek()->format('Y-m-d\TH:i'));

    $this->actingAs(User::factory()->instructor()->create())
        ->post(route('catalogue.enrol-free', $this->course))
        ->assertForbidden();
});

it('refuses a non-student at the action level too', function (): void {
    setPromotion(now()->addWeek()->format('Y-m-d\TH:i'));

    app(EnrolInFreePromotion::class)->handle(User::factory()->instructor()->create(), $this->course);
})->throws(AuthorizationException::class);

it('lets the database hold the new source', function (): void {
    expect(fn () => DB::table('enrollments')->where('id', 0)->update(['source' => 'free_promotion']))->not->toThrow(Exception::class);
});

it('hides purchase on the course page during the promotion and restores it afterwards', function (): void {
    setPromotion(now()->addWeek()->format('Y-m-d\TH:i'));

    $this->actingAs($this->student)->get(route('catalogue.show', $this->course))
        ->assertOk()
        ->assertSee('Enrol free')
        ->assertSee('Free for a limited time')
        ->assertSee((string) $this->course->price)
        ->assertDontSee('Purchase course');

    setPromotion(null);

    $this->actingAs($this->student)->get(route('catalogue.show', $this->course))
        ->assertOk()
        ->assertSee('Purchase course')
        ->assertDontSee('Enrol free');
});

it('offers a guest "Join free" during the promotion', function (): void {
    setPromotion(now()->addWeek()->format('Y-m-d\TH:i'));

    $this->get(route('catalogue.show', $this->course))
        ->assertOk()
        ->assertSee('Join free to enrol')
        ->assertDontSee('Purchase course');
});

it('shows "Go to course" to a student who already has access', function (): void {
    setPromotion(now()->addWeek()->format('Y-m-d\TH:i'));
    Enrollment::factory()->create(['user_id' => $this->student->getKey(), 'course_id' => $this->course->getKey()]);

    $this->actingAs($this->student)->get(route('catalogue.show', $this->course))
        ->assertSee('Go to course')
        ->assertDontSee('Enrol free');
});

it('keeps the paid checkout route working during the promotion', function (): void {
    setPromotion(now()->addWeek()->format('Y-m-d\TH:i'));

    $this->get(route('checkout.start', $this->course))->assertOk();
});

it('hides Payment history during the promotion unless the student has an order', function (): void {
    setPromotion(now()->addWeek()->format('Y-m-d\TH:i'));

    $this->actingAs($this->student)->get(route('student.home'))->assertDontSee('Payment history');

    Order::factory()->create(['user_id' => $this->student->getKey(), 'course_id' => $this->course->getKey()]);

    $this->actingAs($this->student)->get(route('student.home'))->assertSee('Payment history');
});

it('shows Payment history when there is no promotion', function (): void {
    setPromotion(null);

    $this->actingAs($this->student)->get(route('student.home'))->assertSee('Payment history');
});
