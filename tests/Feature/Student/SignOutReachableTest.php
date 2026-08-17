<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\EnrollmentSource;
use App\Models\Course;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Sign out is reachable from every signed-in screen
|--------------------------------------------------------------------------
|
| THIS FILE EXISTS BECAUSE IT WAS ONCE NOT TRUE.
|
| Following the prototype literally — which draws the avatar as a plain disc
| with no menu — left the product with no visible way to sign out from the
| dashboard, My Learning, Certificates or the player. Logout was reachable only
| at the bottom of the profile page, if you had already guessed the disc led
| there. It was reported as "there is no signout option", which is exactly what
| it was.
|
| A design mockup cannot depict an open dropdown, so "the mockup does not show
| one" is not evidence that none should exist. These tests are the guard against
| that reasoning being applied again by someone comparing screens to the
| prototype.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->student()->create();

    $this->course = Course::factory()->published()->create();

    app(GrantEnrollment::class)
        ->handle($this->student, $this->course, EnrollmentSource::AdminGrant, $this->admin);
});

/*
| Route NAMES, resolved inside the test. A dataset closure runs before the
| application is booted, so calling route() there fails with "Target class [url]
| does not exist" — which reads like a container problem rather than what it is.
*/
it('offers sign out on every screen a signed-in student can reach', function (string $name): void {
    $this->actingAs($this->student)
        ->get(route($name))
        ->assertOk()
        ->assertSee('Log out')
        // The form, not just the words — a heading that happened to contain the
        // phrase would satisfy assertSee on its own.
        ->assertSee(route('logout'), false);
})->with([
    'dashboard' => 'student.home',
    'my learning' => 'student.courses.index',
    'certificates' => 'student.certificates.index',
    'profile' => 'profile.show',
    'catalogue' => 'catalogue.index',
]);

it('posts to log out rather than linking to it', function (): void {
    /*
     * A GET route for logout can be triggered by anything that prefetches a
     * URL — a browser, a chat client unfurling a link, a mail scanner — which
     * signs people out at random and is very hard to diagnose from a bug
     * report.
     */
    $this->actingAs($this->student)
        ->get(route('student.home'))
        ->assertOk()
        ->assertSee('method="POST" action="'.route('logout').'"', false);
});

it('names the account the menu belongs to', function (): void {
    // On a shared machine this is the difference between signing out and
    // wondering why someone else's courses are listed.
    $this->actingAs($this->student)
        ->get(route('student.home'))
        ->assertOk()
        ->assertSee($this->student->email);
});

it('shows a guest no sign-out control at all', function (): void {
    $this->get(route('catalogue.index'))
        ->assertOk()
        ->assertDontSee('Log out');
});

it('still offers sign out to an instructor on the public catalogue', function (): void {
    /*
     * Instructors get the PUBLIC header there, not the student one, so this
     * asserts the other branch of that layout still has a way out — the two
     * headers are separate markup and only one of them was fixed.
     */
    $this->actingAs(User::factory()->instructor()->create())
        ->get(route('catalogue.index'))
        ->assertOk()
        ->assertSee(route('logout'), false);
});
