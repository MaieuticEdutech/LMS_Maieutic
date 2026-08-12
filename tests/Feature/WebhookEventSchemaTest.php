<?php

declare(strict_types=1);

use App\Enums\WebhookStatus;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3 (Track C) — webhook_events schema and model invariants
|--------------------------------------------------------------------------
|
| Guards the database-level protections the application relies on but never
| re-checks at runtime (architecture.md §6.4, §6.5, ADR-012).
|
*/

it('creates the webhook_events table', function (): void {
    expect(Schema::hasTable('webhook_events'))->toBeTrue();
});

/*
| IDEMPOTENCY KEY (architecture.md §6.5, Phase 3 DoD — "proven by a test that
| expects the insert to throw"). A replayed webhook delivery must collide on
| this constraint rather than being processed twice.
*/
it('enforces a unique event_id', function (): void {
    WebhookEvent::factory()->create(['event_id' => 'evt_duplicate']);

    expect(fn () => WebhookEvent::factory()->create(['event_id' => 'evt_duplicate']))
        ->toThrow(QueryException::class);
});

/*
| CHECK CONSTRAINT (ADR-012). The database refuses an illegal status even if
| application code is bypassed.
*/
it('rejects an invalid status at the database level', function (): void {
    $event = WebhookEvent::factory()->create();

    expect(fn () => DB::table('webhook_events')->where('id', $event->id)->update(['status' => 'bounced']))
        ->toThrow(QueryException::class);
});

it('accepts every webhook status the application can produce', function (WebhookStatus $status): void {
    $event = WebhookEvent::factory()->create();
    $event->forceFill(['status' => $status])->save();

    expect($event->refresh()->status)->toBe($status);
})->with('webhook statuses');

/*
| CASTS.
*/
it('casts payload, status, attempts and timestamps correctly', function (): void {
    $event = WebhookEvent::factory()->processed()->create([
        'payload' => ['event' => 'order.paid', 'payload' => ['order_id' => 'order_abc123']],
    ]);

    expect($event->payload)->toHaveKey('event', 'order.paid')
        ->and($event->status)->toBe(WebhookStatus::Processed)
        ->and($event->attempts)->toBe(1)
        ->and($event->received_at->isToday())->toBeTrue()
        ->and($event->processed_at?->isToday())->toBeTrue();
});

it('records a failure without a processed_at timestamp', function (): void {
    $event = WebhookEvent::factory()->failed()->create();

    expect($event->status)->toBe(WebhookStatus::Failed)
        ->and($event->attempts)->toBe(5)
        ->and($event->last_error)->not->toBeNull()
        ->and($event->processed_at)->toBeNull();
});

dataset('webhook statuses', fn (): array => WebhookStatus::cases());
