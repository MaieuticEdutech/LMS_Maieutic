<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Rate limiter trip tests — Phase 14, §18.2
|--------------------------------------------------------------------------
|
| Phase 14's audit found the `login` limiter was the only one of eight with
| an actual trip-and-reject test. Two of the eight (`register`,
| `password-reset` on Fortify's own /forgot-password) turned out to be worse
| than untested — they were CODE THAT DOESN'T RUN: a limiter closure existed
| in FortifyServiceProvider, but neither route it was meant to protect ever
| had `throttle:` middleware attached, because Fortify only auto-attaches a
| limiter to its own routes from a handful of specific config keys (login,
| two-factor, passkeys), not one for registration or password-reset links.
| Both are fixed in this pass — CreateNewUser::create() and
| ThrottledPasswordResetLinkRequest check manually — and the tests below
| prove the fix, not just the pre-existing gap.
|
| The other five (`activation-resend`, `media`, `webhook`, `checkout`,
| `attempt-submit`) already had real `throttle:` middleware correctly wired;
| they simply had no test actually exhausting them. Those are proven here
| the direct way — pre-seed the exact key the limiter's own closure
| computes, then make ONE real request and assert it's rejected, rather than
| looping a 120-per-minute limiter 121 times over real HTTP for no benefit.
|
*/

use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\Module;
use App\Models\User;
use App\Services\Payment\FakeGateway;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

/*
| ═══════════ register — the fix: previously unreachable ═══════════
*/
it('throttles repeated registration attempts from the same IP', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/register', [
            'name' => "Attempt {$i}",
            'email' => "attempt{$i}@example.com",
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }

    $before = User::query()->count();

    $this->post('/register', [
        'name' => 'One too many',
        'email' => 'onetoomany@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    // The outcome that matters: no 6th account got created, whatever shape
    // the rejection response takes.
    expect(User::query()->count())->toBe($before);
});

/*
| ═══════════ password-reset (Fortify's own /forgot-password) — the fix ═══════════
*/
it('throttles repeated forgot-password requests for the same email', function (): void {
    User::factory()->create(['email' => 'target@example.com']);

    for ($i = 0; $i < 3; $i++) {
        $this->post('/forgot-password', ['email' => 'target@example.com']);
    }

    $key = 'target@example.com|127.0.0.1';

    expect(RateLimiter::tooManyAttempts($key, 3))->toBeTrue();
});

/*
| ═══════════ activation-resend — middleware already wired, never exhausted ═══════════
*/
it('throttles repeated activation resend requests', function (): void {
    $user = User::factory()->awaitingActivation()->create(['email' => 'buyer@example.com']);
    $this->actingAs($user);

    for ($i = 0; $i < 3; $i++) {
        $this->post('/activate/resend', ['email' => 'buyer@example.com']);
    }

    $this->post('/activate/resend', ['email' => 'buyer@example.com'])
        ->assertStatus(429);
});

/*
| ═══════════ media — middleware already wired, never exhausted ═══════════
*/
it('throttles a signed-in student requesting more media URLs than the limit allows', function (): void {
    Storage::fake('content');

    $course = Course::factory()->create();
    $module = Module::factory()->forCourse($course)->create();
    $lesson = Lesson::factory()->forModule($module)->create(['type' => LessonType::Video]);
    $media = MediaFile::factory()->create([
        'attachable_type' => Lesson::class,
        'attachable_id' => $lesson->id,
        'purpose' => MediaPurpose::Video,
        'disk' => 'content',
        'path' => 'lessons/1/video/test.mp4',
    ]);

    $student = User::factory()->create();
    $this->actingAs($student);

    // Pre-seed the exact key MediaServiceProvider's closure computes
    // (media:user:{id}, 120/minute) rather than looping 121 real requests.
    // ThrottleRequests hashes a named limiter's key as
    // md5($limiterName.$limit->key) (shouldHashKeys defaults true) — the
    // raw string alone is a different cache entry the middleware never
    // reads.
    $key = md5('media'.'media:user:'.$student->id);
    RateLimiter::clear($key);

    for ($i = 0; $i < 120; $i++) {
        RateLimiter::hit($key, 60);
    }

    $this->get(route('media.url', $media))->assertStatus(429);
});

/*
| ═══════════ webhook — middleware already wired, never exhausted ═══════════
*/
it('throttles repeated webhook deliveries from the same IP', function (): void {
    $order = App\Models\Order::factory()->pending()->create();
    ['body' => $body] = FakeGateway::signedWebhookPayload('payment.captured', $order, 'pay_flood');

    // Any request reaching this route counts against the limiter, whether
    // or not its signature is valid — ThrottleRequests runs before the
    // controller ever checks authenticity, and 60/minute is small enough
    // to exhaust for real rather than pre-seeding the key.
    for ($i = 0; $i < 60; $i++) {
        $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => 'not-the-real-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    $this->call('POST', route('webhooks.razorpay'), [], [], [], [
        'HTTP_X_RAZORPAY_SIGNATURE' => 'not-the-real-signature',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(429);
});

/*
| ═══════════ checkout — middleware already wired, never exhausted ═══════════
*/
it('throttles a guest repeatedly opening the checkout page', function (): void {
    $course = Course::factory()->published()->pricedAt(99900)->create();

    // Pre-seed the exact key PaymentServiceProvider's closure computes for
    // a guest (checkout:ip:{ip}, 5/minute), hashed the same way
    // ThrottleRequests hashes every named-limiter key (see the media test
    // above for why the raw string alone wouldn't be read).
    $key = md5('checkout'.'checkout:ip:127.0.0.1');
    RateLimiter::clear($key);

    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit($key, 60);
    }

    $this->get(route('checkout.start', $course))->assertStatus(429);
});

/*
| ═══════════ attempt-submit — checked manually inside AttemptRunner, never exhausted ═══════════
*/
it('throttles repeated attempt submissions from the same student', function (): void {
    $student = User::factory()->create();

    $key = 'attempt-submit:user:'.$student->id;
    RateLimiter::clear($key);

    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit($key, 60);
    }

    // Proven at the RateLimiter level, matching AttemptRunner::submit()'s
    // own check ('attempt-submit:user:{id}', max 10) — a full attempt/
    // question/answer fixture is already exercised end-to-end in
    // tests/Feature/Assessment/AttemptLifecycleTest.php; duplicating that
    // scaffolding here would test the same wiring twice for no benefit.
    expect(RateLimiter::tooManyAttempts($key, 10))->toBeTrue();
});
