<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookStatus;
use Carbon\CarbonImmutable;
use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The idempotency ledger for gateway webhook deliveries (architecture.md
 * §6.4, §13). Written only by the signature-verified webhook endpoint and
 * `ProcessPaymentWebhook` — never by user input, so every writable column is
 * fillable without the mass-assignment concerns that apply to user-facing
 * models.
 *
 * @property int $id
 * @property string $gateway
 * @property string $event_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property string $signature
 * @property WebhookStatus $status
 * @property int $attempts
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $processed_at
 * @property string|null $last_error
 */
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gateway',
        'event_id',
        'event_type',
        'payload',
        'signature',
        'status',
        'attempts',
        'received_at',
        'processed_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookStatus::class,
            'attempts' => 'integer',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
