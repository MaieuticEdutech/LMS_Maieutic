<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes append-only audit entries (NFR-SEC-16, architecture.md §18.4).
 *
 * CALLED FROM ACTIONS, NEVER FROM CONTROLLERS (planning.md §18.4). An Action
 * is the only place that knows a change actually succeeded and what it
 * changed; a controller only knows a request arrived.
 *
 * WHAT MUST BE AUDITED:
 *   authentication success and failure, role and status changes, user create
 *   and delete, course publish/unpublish/delete, content deletion, enrollment
 *   grant/revoke, order and payment state changes, webhook signature
 *   failures, settings changes, report exports.
 *
 * WHAT MUST NEVER REACH THIS LOG (NFR-DATA-03):
 *   passwords (raw or hashed), tokens, gateway secrets, session identifiers,
 *   complete personal records. The `changes` payload is filtered on write.
 */
final class AuditLogger
{
    /**
     * Attribute names that are never written to the audit log, even if a
     * caller passes them. Defence against a future caller handing us
     * $model->getChanges() wholesale on a model that holds secrets.
     *
     * @var list<string>
     */
    private const REDACTED = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'api_token',
        'secret',
        'key_secret',
        'webhook_secret',
        'signature',
    ];

    public function __construct(private readonly Request $request) {}

    /**
     * Record an action.
     *
     * @param  string  $action  Dot-notated verb, e.g. 'auth.login.succeeded'.
     * @param  User|null  $actor  The user who performed it; null for system or anonymous actions.
     * @param  Model|null  $subject  The record acted upon, if any.
     * @param  array{before?: array<string, mixed>, after?: array<string, mixed>}  $changes
     */
    public function record(
        string $action,
        ?User $actor = null,
        ?Model $subject = null,
        array $changes = [],
        ?string $description = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => $subject !== null ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'description' => $description,
            'changes' => $changes === [] ? null : $this->redact($changes),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->truncate($this->request->userAgent()),
        ]);
    }

    /**
     * Record a security-relevant failure where there may be no authenticated
     * actor — a failed login, a rejected webhook signature, a denied request.
     *
     * The email is recorded in the description rather than resolved to a user,
     * because the whole point is that it may not correspond to a real account.
     */
    public function recordSecurityEvent(string $action, string $description): AuditLog
    {
        return $this->record(
            action: $action,
            description: $description,
        );
    }

    /**
     * Strip secrets from a {before, after} payload.
     *
     * Values are replaced with a marker rather than removed, so the log still
     * shows THAT a sensitive field changed without revealing its value — which
     * is exactly what an auditor needs to know about a password change.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function redact(array $changes): array
    {
        foreach ($changes as $key => $value) {
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $changes[$key] = $this->redact($value);

                continue;
            }

            if (in_array(mb_strtolower((string) $key), self::REDACTED, true)) {
                $changes[$key] = '[redacted]';
            }
        }

        return $changes;
    }

    private function truncate(?string $value, int $limit = 512): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }
}
