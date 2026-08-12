<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * An append-only audit entry (NFR-SEC-16, NFR-SEC-17).
 *
 * IMMUTABILITY IS ENFORCED IN CODE, NOT JUST BY CONVENTION.
 * The `updating` and `deleting` model events throw. An audit log that can be
 * quietly rewritten is worse than no audit log, because it carries the
 * authority of a record while providing none of the guarantee. If a future
 * developer writes `$log->update([...])`, they get an exception at the point
 * of the mistake rather than a silently corrupted trail.
 *
 * Entries are written ONLY by App\Services\Audit\AuditLogger. AuditLogPolicy
 * denies `create` to every user, because writing an entry is never a user
 * action — it is a side effect of one.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property string|null $description
 * @property array<string, mixed>|null $changes
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable $created_at
 */
class AuditLog extends Model
{
    /**
     * There is no `updated_at` column — the table is append-only.
     */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'changes',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException(
                'Audit log entries are append-only and cannot be modified. '
                .'If a correction is needed, write a new entry describing it.',
            );
        });

        static::deleting(static function (): never {
            throw new LogicException(
                'Audit log entries are append-only and cannot be deleted. '
                .'Retention is handled by a documented archival process, not by application code.',
            );
        });
    }

    /**
     * The actor. Nullable and SET NULL on delete: removing a user must never
     * erase the record of what they did.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * The subject of the action, if it had one.
     *
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
