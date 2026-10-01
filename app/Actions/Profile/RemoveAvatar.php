<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Profile\ProfileDocumentStorage;
use Illuminate\Support\Facades\DB;

/**
 * Remove a learner's profile photo. A no-op, not an error, if none is stored.
 */
final class RemoveAvatar
{
    public function __construct(
        private readonly ProfileDocumentStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user): User
    {
        $path = $user->getAttributes()['avatar_path'] ?? null;

        if ($path === null) {
            return $user;
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'avatar_path' => null,
                'avatar_original_name' => null,
                'avatar_mime_type' => null,
                'avatar_size_bytes' => null,
            ])->save();
        });

        $this->storage->delete($path);

        $this->audit->record(
            action: 'profile.avatar_removed',
            actor: $user,
            subject: $user,
            description: 'Removed their profile photo.',
        );

        return $user;
    }
}
