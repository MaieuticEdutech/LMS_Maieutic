<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Actions\Enrollment\RevokeEnrollment;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaFile;
use App\Models\Module;
use App\Models\User;
use App\Services\Enrollment\EnrollmentAccessService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/*
|--------------------------------------------------------------------------
| Phase 6 · Protected content delivery (AC-01, AC-02, AC-19, FR-FILE-06…09)
|--------------------------------------------------------------------------
|
| This is the file that proves the product's central promise: paid content is
| unreachable without a live enrollment, by ANY URL, at ANY time.
|
| Every test here attacks the real HTTP routes rather than calling the policy
| directly. A policy that returns false while a route serves bytes anyway is
| precisely the failure mode worth testing for, and it is invisible to a unit
| test of the policy.
|
*/

beforeEach(function (): void {
    Storage::fake('content');

    $this->course = Course::factory()->create();
    $module = Module::factory()->forCourse($this->course)->create();
    $this->lesson = Lesson::factory()->forModule($module)->create(['type' => LessonType::Video]);

    $this->student = User::factory()->create();
    $this->admin = User::factory()->superAdmin()->create();

    // A real file on the fake disk, so streaming has bytes to serve.
    $this->bytes = str_repeat('A', 1000).str_repeat('B', 1000);

    $this->media = MediaFile::factory()->create([
        'attachable_type' => Lesson::class,
        'attachable_id' => $this->lesson->id,
        'purpose' => MediaPurpose::Video,
        'disk' => 'content',
        'path' => 'lessons/1/video/test.mp4',
        'mime_type' => 'video/mp4',
        'size_bytes' => strlen($this->bytes),
        'is_downloadable' => false,
    ]);

    Storage::disk('content')->put($this->media->path, $this->bytes);

    $this->enroll = fn (User $u) => app(GrantEnrollment::class)
        ->handle($u, $this->course, EnrollmentSource::AdminGrant, $this->admin);
});

/*
| ═══════════════════ AC-01 — GUESTS REACH NOTHING ═══════════════════
*/
it('redirects a guest away from the url route', function (): void {
    $this->get(route('media.url', $this->media))->assertRedirect('/login');
});

it('refuses a guest even with a validly signed stream url', function (): void {
    // The signature is genuine — we mint it ourselves. It proves the URL came
    // from us; it says nothing about who is holding it. The policy is what
    // refuses, and this is the test that proves the signature alone is not
    // treated as authorisation.
    $signed = URL::temporarySignedRoute('media.stream', now()->addMinutes(5), ['media' => $this->media->getRouteKey()]);

    $this->get($signed)->assertForbidden();
});

/*
| ═══════════════════ AC-02 — UNENROLLED STUDENTS REACH NOTHING ═══════
*/
it('refuses an authenticated but unenrolled student', function (): void {
    $this->actingAs($this->student)
        ->getJson(route('media.url', $this->media))
        ->assertForbidden();
});

it('refuses an unenrolled student holding a signed stream url', function (): void {
    $signed = URL::temporarySignedRoute('media.stream', now()->addMinutes(5), ['media' => $this->media->getRouteKey()]);

    $this->actingAs($this->student)->get($signed)->assertForbidden();
});

it('refuses a student enrolled in a DIFFERENT course', function (): void {
    // Enrollment is per course, not a membership tier.
    $other = Course::factory()->create();
    app(GrantEnrollment::class)->handle($this->student, $other, EnrollmentSource::Purchase);

    $this->actingAs($this->student)
        ->getJson(route('media.url', $this->media))
        ->assertForbidden();
});

/*
| ═══════════════════ ENROLLED STUDENTS DO REACH IT ═══════════════════
|
| A gate that denies everyone is not secure, it is broken. These are the
| tests that keep the ones above honest.
*/
it('issues a url to an enrolled student', function (): void {
    ($this->enroll)($this->student);

    $this->actingAs($this->student)
        ->getJson(route('media.url', $this->media))
        ->assertOk()
        ->assertJsonStructure(['url', 'expires_in', 'is_downloadable']);
});

it('serves bytes to an enrolled student', function (): void {
    ($this->enroll)($this->student);

    $signed = URL::temporarySignedRoute('media.stream', now()->addMinutes(5), ['media' => $this->media->getRouteKey()]);

    $response = $this->actingAs($this->student)->get($signed);

    $response->assertOk()
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->streamedContent())->toBe($this->bytes);
});

it('lets an admin through without any enrollment', function (): void {
    $this->actingAs($this->admin)
        ->getJson(route('media.url', $this->media))
        ->assertOk();
});

/*
| ═══════════════════ AC-19 — URLS EXPIRE ═══════════════════
*/
it('refuses a signed url once its ttl has passed', function (): void {
    ($this->enroll)($this->student);

    $signed = URL::temporarySignedRoute('media.stream', now()->addMinutes(5), ['media' => $this->media->getRouteKey()]);

    // A URL that leaked is only dangerous while it works.
    $this->travel(6)->minutes();

    $this->actingAs($this->student)->get($signed)->assertForbidden();
});

it('refuses a tampered signature', function (): void {
    ($this->enroll)($this->student);

    $signed = URL::temporarySignedRoute('media.stream', now()->addMinutes(5), ['media' => $this->media->getRouteKey()]);

    $this->actingAs($this->student)->get($signed.'&extra=1')->assertForbidden();
});

it('caps the issued ttl at the security ceiling', function (): void {
    // NFR-SEC-22. Config already clamps, but a mistaken environment value must
    // not be able to widen the window.
    config()->set('lms.media.url_ttl', 86400);
    ($this->enroll)($this->student);

    $this->actingAs($this->student)
        ->getJson(route('media.url', $this->media))
        ->assertOk()
        ->assertJsonPath('expires_in', 300);
});

/*
| ═══════════════════ REVOCATION IS IMMEDIATE ═══════════════════
*/
it('stops serving the moment access is revoked, even on an unexpired url', function (): void {
    $enrollment = ($this->enroll)($this->student);

    $signed = URL::temporarySignedRoute('media.stream', now()->addMinutes(5), ['media' => $this->media->getRouteKey()]);

    $this->actingAs($this->student)->get($signed)->assertOk();

    app(RevokeEnrollment::class)->handle($enrollment, $this->admin, 'Chargeback received.');

    // The URL is still validly signed and still unexpired. Access is gone
    // anyway, because the controller re-checks the policy on every request
    // rather than trusting the signature (FR-ENR-08).
    $this->actingAs($this->student)->get($signed)->assertForbidden();
});

it('denies every non-granting status', function (EnrollmentStatus $status): void {
    $enrollment = ($this->enroll)($this->student);
    $enrollment->forceFill(['status' => $status])->save();
    app(EnrollmentAccessService::class)->flush();

    $this->actingAs($this->student)
        ->getJson(route('media.url', $this->media))
        ->assertForbidden();
})->with([
    'suspended' => EnrollmentStatus::Suspended,
    'expired' => EnrollmentStatus::Expired,
    'refunded' => EnrollmentStatus::Refunded,
]);

it('allows completed, because finishing a course does not end access', function (): void {
    $enrollment = ($this->enroll)($this->student);
    $enrollment->forceFill(['status' => EnrollmentStatus::Completed, 'completed_at' => now()])->save();
    app(EnrollmentAccessService::class)->flush();

    $this->actingAs($this->student)
        ->getJson(route('media.url', $this->media))
        ->assertOk();
});

it('denies an enrollment whose expiry has passed, without waiting for the scheduler', function (): void {
    // The status column still says `active` — the hourly command has not run.
    // Access must already be gone, because the date is the authority and the
    // status is only a cache of it.
    $enrollment = ($this->enroll)($this->student);
    $enrollment->forceFill(['expires_at' => now()->subMinute()])->save();
    app(EnrollmentAccessService::class)->flush();

    expect($enrollment->refresh()->status)->toBe(EnrollmentStatus::Active);

    $this->actingAs($this->student)
        ->getJson(route('media.url', $this->media))
        ->assertForbidden();
});

/*
| ═══════════════════ HTTP RANGE (FR-FILE-08) ═══════════════════
|
| Without Range, seeking a lecture means re-downloading it from the start.
*/
it('answers a range request with 206 and the exact bytes', function (): void {
    ($this->enroll)($this->student);

    $signed = URL::temporarySignedRoute('media.stream', now()->addMinutes(5), ['media' => $this->media->getRouteKey()]);

    $response = $this->actingAs($this->student)->get($signed, ['Range' => 'bytes=1000-1099']);

    $response->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 1000-1099/2000')
        ->assertHeader('Content-Length', '100');

    // Byte 1000 onwards is the 'B' half of the fixture.
    expect($response->streamedContent())->toBe(str_repeat('B', 100));
});

it('handles an open-ended range', function (): void {
    ($this->enroll)($this->student);

    $signed = URL::temporarySignedRoute('media.stream', now()->addMinutes(5), ['media' => $this->media->getRouteKey()]);

    $response = $this->actingAs($this->student)->get($signed, ['Range' => 'bytes=1500-']);

    $response->assertStatus(206)->assertHeader('Content-Range', 'bytes 1500-1999/2000');
    expect(strlen($response->streamedContent()))->toBe(500);
});

it('handles a suffix range, which players use to read the mp4 moov atom', function (): void {
    ($this->enroll)($this->student);

    $signed = URL::temporarySignedRoute('media.stream', now()->addMinutes(5), ['media' => $this->media->getRouteKey()]);

    $response = $this->actingAs($this->student)->get($signed, ['Range' => 'bytes=-200']);

    $response->assertStatus(206)->assertHeader('Content-Range', 'bytes 1800-1999/2000');
});

it('clamps a range that runs past the end of the file', function (): void {
    ($this->enroll)($this->student);

    $signed = URL::temporarySignedRoute('media.stream', now()->addMinutes(5), ['media' => $this->media->getRouteKey()]);

    $response = $this->actingAs($this->student)->get($signed, ['Range' => 'bytes=1900-99999']);

    $response->assertStatus(206)->assertHeader('Content-Range', 'bytes 1900-1999/2000');
});

it('falls back to the whole file for a malformed range', function (string $header): void {
    ($this->enroll)($this->student);

    $signed = URL::temporarySignedRoute('media.stream', now()->addMinutes(5), ['media' => $this->media->getRouteKey()]);

    // Serving everything is a correct response to a request we cannot parse,
    // and far better than failing.
    $this->actingAs($this->student)->get($signed, ['Range' => $header])->assertOk();
})->with(['bytes=abc-def', 'items=0-99', 'bytes=', 'bytes=5000-100']);

/*
| ═══════════════════ NO BACK DOOR ═══════════════════
*/
it('has no route that serves protected content without the policy', function (): void {
    // AC-20. Laravel's local-disk serving route would bypass every check
    // above, which is why `serve => false` is set on the content disk.
    expect(config('filesystems.disks.content.serve'))->toBeFalse()
        ->and(config('filesystems.disks.content.visibility'))->toBe('private');

    $response = $this->get('/storage/'.$this->media->path);

    // 403 or 404 — either is a correct refusal, and which one Laravel picks is
    // its business. Asserting a specific code would make this test fail on a
    // framework upgrade that changed nothing about the security property.
    //
    // What must be true is that the bytes do not come back.
    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getContent())->not->toBe($this->bytes);
});
