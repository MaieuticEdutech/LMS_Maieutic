<?php

declare(strict_types=1);

use App\Enums\EmailStatus;
use App\Livewire\Admin\EmailLogTable;
use App\Models\EmailLog;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Phase 11 · Email log viewer (FR-MAIL-10)
|--------------------------------------------------------------------------
|
| `email_logs` has been written since Phase 11's infrastructure landed, and
| read by nothing. A delivery record nobody can look at answers no question.
|
| The question it exists to answer is "the student says they never got the
| email" — so these tests are about finding one row among many, seeing why it
| failed, and being unable to change any of it.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();

    $this->log = fn (array $attributes = []): EmailLog => EmailLog::query()->create(array_merge([
        'to_email' => 'ada@example.test',
        'mailable' => 'App\\Notifications\\EnrollmentGrantedNotification',
        'subject' => 'You now have access to Statistics I',
        'status' => EmailStatus::Sent,
        'sent_at' => now(),
    ], $attributes));
});

/*
| ═══════════════ ACCESS ═══════════════
*/
it('is closed to everyone but a super admin', function (string $factoryState): void {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user)->get(route('admin.email-log.index'))->assertForbidden();
})->with(['student', 'instructor']);

it('redirects a guest to login', function (): void {
    $this->get(route('admin.email-log.index'))->assertRedirect('/login');
});

it('opens for a super admin', function (): void {
    $this->actingAs($this->admin)->get(route('admin.email-log.index'))->assertOk();
});

/*
| ═══════════════ ANSWERING THE SUPPORT QUESTION ═══════════════
*/
it('shows a logged email with its recipient, subject and outcome', function (): void {
    ($this->log)();

    $this->actingAs($this->admin);

    Livewire::test(EmailLogTable::class)
        ->assertSee('ada@example.test')
        ->assertSee('You now have access to Statistics I')
        // Class basename, not the namespace — the namespace is noise in a table.
        ->assertSee('EnrollmentGrantedNotification')
        ->assertSee('Sent');
});

it('finds one row by recipient among many', function (): void {
    ($this->log)(['to_email' => 'ada@example.test']);
    ($this->log)(['to_email' => 'grace@example.test']);

    $this->actingAs($this->admin);

    Livewire::test(EmailLogTable::class)
        ->set('search', 'grace')
        ->assertSee('grace@example.test')
        ->assertDontSee('ada@example.test');
});

it('shows why a message failed, next to the message that failed', function (): void {
    ($this->log)([
        'status' => EmailStatus::Failed,
        'error' => 'Connection could not be established with host smtp.example.test',
        'sent_at' => null,
    ]);

    $this->actingAs($this->admin);

    // A failure without its reason sends someone to the logs; a reason on a
    // separate screen is a reason nobody reads.
    Livewire::test(EmailLogTable::class)
        ->assertSee('Failed')
        ->assertSee('Connection could not be established');
});

it('narrows to failures in one click', function (): void {
    ($this->log)(['subject' => 'A message that went out']);
    ($this->log)(['subject' => 'A message that did not', 'status' => EmailStatus::Failed, 'sent_at' => null]);

    $this->actingAs($this->admin);

    Livewire::test(EmailLogTable::class)
        ->call('showFailuresOnly')
        ->assertSee('A message that did not')
        ->assertDontSee('A message that went out');
});

it('counts every state, whatever the filter says', function (): void {
    ($this->log)();
    ($this->log)();
    ($this->log)(['status' => EmailStatus::Failed, 'sent_at' => null]);
    ($this->log)(['status' => EmailStatus::Queued, 'sent_at' => null]);

    /*
     * Called on the component directly rather than through a render cycle.
     * summary() and rows() are public for exactly this reason (WithAdminTable's
     * docblock): they are query logic with nothing to do with rendering, and
     * `->instance()` returns the base Component type, which loses every method
     * being tested here.
     */
    $component = new EmailLogTable;
    $component->statusFilter = EmailStatus::Failed->value;

    // A "failed" tile that changed when you filtered to failures would be
    // telling you nothing.
    expect($component->summary())->toMatchArray(['sent' => 2, 'failed' => 1, 'queued' => 1, 'total' => 4]);
});

it('puts the newest message first', function (): void {
    // created_at is not fillable — it is the framework's, not the log's — so
    // it is backdated after the row exists.
    ($this->log)(['subject' => 'Older'])->forceFill(['created_at' => now()->subDay()])->save();
    ($this->log)(['subject' => 'Newer']);

    expect((new EmailLogTable)->rows()->first()?->subject)->toBe('Newer');
});

it('says so plainly when nothing has been sent yet', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(EmailLogTable::class)->assertSee('No emails logged yet');
});

/*
| ═══════════════ IT IS EVIDENCE, SO IT IS IMMUTABLE ═══════════════
*/
it('denies every mutation, including to a super admin', function (string $ability): void {
    $log = ($this->log)();

    /*
     * Rows are written by LogOutboundEmail from the transport events; they
     * record something that happened, not something a user does. An editable
     * delivery log would carry the authority of evidence and none of the
     * guarantee — the same reasoning as the audit log.
     */
    expect($this->admin->can($ability, $log))->toBeFalse();
})->with(['update', 'delete']);

it('denies creating a log row by hand', function (): void {
    expect($this->admin->can('create', EmailLog::class))->toBeFalse();
});

it('resolves the policy rather than falling through to a default', function (): void {
    $student = User::factory()->student()->create();

    expect($this->admin->can('viewAny', EmailLog::class))->toBeTrue()
        ->and($student->can('viewAny', EmailLog::class))->toBeFalse();
});
