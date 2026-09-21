<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Jobs\Payment\ProcessPaymentWebhook;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\Payment\FakeGateway;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Phase 12 — ProcessRazorpayWebhookController
|--------------------------------------------------------------------------
|
| Raw, byte-exact request bodies throughout, via $this->call() rather than
| postJson() — the whole point of this endpoint is that authenticity comes
| from an HMAC over the EXACT bytes Razorpay sent, so a test that lets
| Laravel's JSON helpers re-encode the payload would not actually prove the
| signature check works, only that some JSON round-tripped correctly
| (architecture.md §11.3 rule 3).
|
| $this->call() inline in every test rather than a shared top-level helper
| function: a plain function calling test()/$this from outside a Pest
| closure is not something the Pest PHPStan plugin can type — it only
| resolves $this inside an it()/test() closure itself.
*/

it('rejects a webhook with an invalid signature and creates no record', function (): void {
    $order = Order::factory()->pending()->create();
    ['body' => $body] = FakeGateway::signedWebhookPayload('payment.captured', $order, 'pay_bad_sig');

    $this->call('POST', route('webhooks.razorpay'), [], [], [], [
        'HTTP_X_RAZORPAY_SIGNATURE' => 'not-the-real-signature',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(400);

    expect(WebhookEvent::query()->count())->toBe(0);
});

it('rejects a webhook with no signature header at all', function (): void {
    $order = Order::factory()->pending()->create();
    ['body' => $body] = FakeGateway::signedWebhookPayload('payment.captured', $order, 'pay_no_sig');

    $this->call('POST', route('webhooks.razorpay'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(400);

    expect(WebhookEvent::query()->count())->toBe(0);
});

it('accepts a validly signed webhook, persists it, and dispatches processing', function (): void {
    Queue::fake();

    $order = Order::factory()->pending()->create();
    ['body' => $body, 'signature' => $signature] = FakeGateway::signedWebhookPayload('payment.captured', $order, 'pay_valid');

    $this->call('POST', route('webhooks.razorpay'), [], [], [], [
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect(WebhookEvent::query()->count())->toBe(1)
        ->and(WebhookEvent::query()->first()?->event_type)->toBe('payment.captured');

    Queue::assertPushed(ProcessPaymentWebhook::class, 1);
});

it('acknowledges a retried delivery without a second row or a second dispatch', function (): void {
    Queue::fake();

    $order = Order::factory()->pending()->create();
    ['body' => $body, 'signature' => $signature] = FakeGateway::signedWebhookPayload('payment.captured', $order, 'pay_retried');

    $headers = ['HTTP_X_RAZORPAY_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'];

    $this->call('POST', route('webhooks.razorpay'), [], [], [], $headers, $body)->assertOk();
    $this->call('POST', route('webhooks.razorpay'), [], [], [], $headers, $body)->assertOk();

    expect(WebhookEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessPaymentWebhook::class, 1);
});

it('end to end: a delivered captured-payment webhook settles the order and grants one enrollment', function (): void {
    // Queue is 'sync' in testing (phpunit.xml), so this genuinely exercises
    // controller -> job -> SettleCapturedPayment -> GrantEnrollment, not a
    // faked stand-in for any of it.
    $course = Course::factory()->published()->pricedAt(99900)->create();
    $order = Order::factory()->for($course)->pending()->create(['amount_total' => 99900]);

    ['body' => $body, 'signature' => $signature] = FakeGateway::signedWebhookPayload('payment.captured', $order, 'pay_e2e');

    $this->call('POST', route('webhooks.razorpay'), [], [], [], [
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(Enrollment::query()->where('course_id', $course->getKey())->count())->toBe(1);

    $webhookEvent = WebhookEvent::query()->first();
    expect($webhookEvent?->status->value)->toBe('processed');
});

it('end to end: a delivered failed-payment webhook fails the order and grants nothing', function (): void {
    $course = Course::factory()->published()->pricedAt(99900)->create();
    $order = Order::factory()->for($course)->pending()->create(['amount_total' => 99900]);

    ['body' => $body, 'signature' => $signature] = FakeGateway::signedWebhookPayload('payment.failed', $order, 'pay_e2e_failed');

    $this->call('POST', route('webhooks.razorpay'), [], [], [], [
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Failed)
        ->and(Enrollment::query()->where('course_id', $course->getKey())->count())->toBe(0);
});

it('records an event type it does not act on as ignored, rather than dropping it silently', function (): void {
    $order = Order::factory()->pending()->create();
    ['body' => $body, 'signature' => $signature] = FakeGateway::signedWebhookPayload('refund.processed', $order, 'pay_refund');

    $this->call('POST', route('webhooks.razorpay'), [], [], [], [
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $webhookEvent = WebhookEvent::query()->first();
    expect($webhookEvent?->status->value)->toBe('ignored');
});
