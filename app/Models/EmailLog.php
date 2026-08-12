<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailStatus;
use Carbon\CarbonImmutable;
use Database\Factories\EmailLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A record of one outbound mailable dispatch (architecture.md §6.4, §14,
 * FR-MAIL-10). Written only by `SendMailJob` — never by user input, so every
 * writable column is fillable without the mass-assignment concerns that
 * apply to user-facing models.
 *
 * @property int $id
 * @property string $to_email
 * @property string $mailable
 * @property string $subject
 * @property EmailStatus $status
 * @property string|null $error
 * @property CarbonImmutable|null $sent_at
 * @property array<string, mixed>|null $context
 */
class EmailLog extends Model
{
    /** @use HasFactory<EmailLogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'to_email',
        'mailable',
        'subject',
        'status',
        'error',
        'sent_at',
        'context',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmailStatus::class,
            'sent_at' => 'immutable_datetime',
            'context' => 'array',
        ];
    }
}
