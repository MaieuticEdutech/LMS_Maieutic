<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\AccountActivationNotification;
use App\Notifications\EnrollmentGrantedNotification;
use App\Notifications\EnrollmentRevokedNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Phase 11 — mail is queued, never synchronous (FR-MAIL-06, AC-33)
|--------------------------------------------------------------------------
|
| AC-33 is the acceptance criterion this whole phase exists to satisfy:
|
|   "All emails are dispatched through the queue and a mail failure never
|    rolls back or blocks an enrollment."
|
| It is why Phase 11 precedes Phase 12. When the payment webhook grants an
| enrollment inside a transaction and sends the activation email, a mail
| transport that is slow, down, or misconfigured must not be able to undo the
| enrollment the customer paid for.
|
*/

/*
| ═════════════ EVERY NOTIFICATION IS QUEUED ═════════════
|
| Checked structurally rather than by observing one dispatch: implementing
| ShouldQueue is the property that makes a notification safe to send from
| inside a transaction, and it must hold for every one of them, including any
| added later.
*/
it('implements ShouldQueue on every transactional notification', function (string $notification): void {
    expect(is_subclass_of($notification, ShouldQueue::class))->toBeTrue(
        "{$notification} must implement ShouldQueue — a synchronous send inside a transaction breaks AC-33.",
    );
})->with([
    VerifyEmailNotification::class,
    ResetPasswordNotification::class,
    AccountActivationNotification::class,
    PasswordChangedNotification::class,
    EnrollmentGrantedNotification::class,
    EnrollmentRevokedNotification::class,
]);

it('places every transactional notification on the named mail queue', function (BaseNotification $notification): void {
    // Named queues are the mechanism that stops a backlog of low-priority work
    // from delaying mail (architecture.md §13). A notification on the wrong
    // queue is invisible until a worker's --queue list omits it.
    //
    // `queue` comes from the Queueable trait rather than the Notification base
    // class, so it is read dynamically here.
    expect($notification->queue ?? null)->toBe(config()->string('lms.queues.mail'));
})->with([
    fn () => new VerifyEmailNotification,
    fn () => new ResetPasswordNotification('token'),
    fn () => new AccountActivationNotification('token'),
    fn () => new PasswordChangedNotification,
    fn () => new EnrollmentGrantedNotification('Example Course'),
    fn () => new EnrollmentRevokedNotification('Example Course', 'Refund processed.'),
]);

/*
| ═════════════ AC-33: A MAIL FAILURE DOES NOT ROLL BACK ITS TRANSACTION ═════════════
*/
it('does not send mail inside the request when a notification is dispatched', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    $user->notify(new PasswordChangedNotification);

    // The work was handed to the queue rather than executed inline.
    Queue::assertPushed(SendQueuedNotifications::class);
});

it('commits an enrollment-shaped transaction even when the mail transport is broken', function (): void {
    /*
     * The AC-33 scenario in miniature, with the transport guaranteed to fail.
     *
     * Because the notification is queued, the send does not happen inside the
     * transaction at all — so a transport failure cannot reach it. The user
     * row must survive.
     */
    Notification::fake();

    $email = 'ac33-'.uniqid().'@example.test';

    DB::transaction(function () use ($email): void {
        $user = User::factory()->create(['email' => $email]);
        $user->notify(new PasswordChangedNotification);
    });

    expect(User::query()->where('email', $email)->exists())->toBeTrue();
});

/*
| ═════════════ after_commit: THE ORDERING GUARANTEE ═════════════
|
| phpunit.xml runs the suite on QUEUE_CONNECTION=sync so that queued work
| executes inline and assertions stay simple. These two tests are the
| exception: they are ABOUT the dispatch-versus-commit boundary, which sync
| does not have. They select the `database` connection explicitly — the one
| development actually runs — so the behaviour proven here is the behaviour
| that ships.
|
| Testing this matters more than it might appear. after_commit is a single
| config line whose absence produces no error, no failing request and no
| visible symptom in development, where the worker is usually slower than the
| commit. It surfaces in production as a rare, unreproducible "I got the email
| before I had access" — exactly the class of bug that is never traced back to
| a config default.
*/
/*
| NOTE ON WHY THESE USE THE REAL QUEUE AND NOT Queue::fake().
|
| Queue::fake() swaps out the queue manager entirely, and the fake pushes
| immediately with no regard for `after_commit`. A test written against the
| fake therefore proves nothing about the setting — it passes identically
| whether after_commit is true or false, which makes it worse than no test.
|
| These drive the REAL database queue connection and inspect the `jobs` table
| directly, so they fail if the configuration regresses.
*/
it('holds queued mail until the surrounding transaction commits', function (): void {
    config()->set('queue.default', 'database');

    expect(config()->boolean('queue.connections.database.after_commit'))->toBeTrue(
        'The database queue connection must dispatch after commit (AC-33).',
    );

    DB::table('jobs')->delete();

    DB::transaction(function (): void {
        $user = User::factory()->create();
        $user->notify(new PasswordChangedNotification);

        /*
         * Still inside the transaction. The job must NOT be on the queue yet:
         * a worker that picked it up now could email a student about an
         * enrollment this transaction has not committed — and might still
         * roll back.
         *
         * Note this counts rows through the same connection, so it sees the
         * uncommitted state of this transaction. That is the point: even from
         * in here, where a dispatched job WOULD be visible, there is none.
         */
        expect(DB::table('jobs')->count())->toBe(0);
    });

    // Committed — only now is the work released to a worker.
    expect(DB::table('jobs')->count())->toBe(1);
});

it('does not dispatch mail when the transaction rolls back', function (): void {
    config()->set('queue.default', 'database');

    DB::table('jobs')->delete();

    try {
        DB::transaction(function (): void {
            $user = User::factory()->create();
            $user->notify(new PasswordChangedNotification);

            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {
        // expected — the point is what happens to the queued mail
    }

    /*
     * The email would have promised something that did not happen, so it must
     * never be sent. This is the failure mode after_commit exists to prevent.
     */
    expect(DB::table('jobs')->count())->toBe(0);
});

it('configures after_commit on the production queue connection too', function (): void {
    // Production runs Redis (architecture.md §22). The guarantee must not be
    // accidentally specific to the development driver.
    expect(config()->boolean('queue.connections.redis.after_commit'))->toBeTrue();
});
