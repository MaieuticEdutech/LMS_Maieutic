<?php

declare(strict_types=1);

use App\Enums\WebhookStatus;
use App\Livewire\Admin\WebhookEventsTable;
use App\Models\User;
use App\Models\WebhookEvent;
use Livewire\Livewire;

it('lists webhook events with their type and status', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    WebhookEvent::factory()->processed()->create([
        'event_id' => 'evt_findme001',
        'event_type' => 'payment.captured',
    ]);

    Livewire::test(WebhookEventsTable::class)
        ->assertSee('evt_findme001')
        ->assertSee('payment.captured')
        ->assertSee('Processed');
});

it('searches by event id or event type', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    WebhookEvent::factory()->create(['event_id' => 'evt_aaaaaaaa1', 'event_type' => 'payment.captured']);
    WebhookEvent::factory()->create(['event_id' => 'evt_bbbbbbbb2', 'event_type' => 'payment.failed']);

    Livewire::test(WebhookEventsTable::class)
        ->set('search', 'aaaaaaaa1')
        ->assertSee('evt_aaaaaaaa1')
        ->assertDontSee('evt_bbbbbbbb2');
});

it('filters by status', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    WebhookEvent::factory()->processed()->create(['event_id' => 'evt_processed1']);
    WebhookEvent::factory()->failed()->create(['event_id' => 'evt_failedone1']);

    Livewire::test(WebhookEventsTable::class)
        ->set('statusFilter', WebhookStatus::Processed->value)
        ->assertSee('evt_processed1')
        ->assertDontSee('evt_failedone1');
});

it('shows failures only with one click', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    WebhookEvent::factory()->processed()->create(['event_id' => 'evt_processed2']);
    WebhookEvent::factory()->failed()->create(['event_id' => 'evt_failedtwo2']);

    Livewire::test(WebhookEventsTable::class)
        ->call('showFailuresOnly')
        ->assertSee('evt_failedtwo2')
        ->assertDontSee('evt_processed2');
});

it('shows why a delivery failed and its raw payload', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    WebhookEvent::factory()->failed()->create([
        'event_id' => 'evt_withreason1',
        'last_error' => 'Signature verification failed.',
    ]);

    Livewire::test(WebhookEventsTable::class)
        ->assertSee('Why it failed')
        ->assertSee('Signature verification failed.')
        ->assertSee('Raw payload');
});

it('shows an empty state with no webhook events', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(WebhookEventsTable::class)->assertSee('No webhook deliveries yet');
});

it('denies an instructor and a student from viewing the table', function (): void {
    $instructor = User::factory()->instructor()->create();
    $this->actingAs($instructor)->get(route('admin.webhook-events.index'))->assertForbidden();

    $student = User::factory()->student()->create();
    $this->actingAs($student)->get(route('admin.webhook-events.index'))->assertForbidden();
});

it('never lets anyone mutate a webhook event, not even a super admin', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $event = WebhookEvent::factory()->create();

    expect($admin->can('create', WebhookEvent::class))->toBeFalse()
        ->and($admin->can('update', $event))->toBeFalse()
        ->and($admin->can('delete', $event))->toBeFalse();
});
