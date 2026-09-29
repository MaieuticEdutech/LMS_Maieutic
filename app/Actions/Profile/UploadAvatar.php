<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Profile\ProfileDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Store a learner's profile photo, replacing any existing one.
 *
 * `avatar_path` and its metadata are set with forceFill(), not fill(): they
 * are not on User::$fillable (see the 2026_09_28_100500 migration) precisely
 * so nothing but this action can point them at a file. A path here always
 * comes from a file this action itself just wrote, never from request input.
 */
final class UploadAvatar
{
    public function __construct(
        private readonly ProfileDocumentStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, UploadedFile $file): User
    {
        $previousPath = $user->getAttributes()['avatar_path'] ?? null;

        $stored = $this->storage->store($file, 'avatars/'.$user->getKey());

        DB::transaction(function () use ($user, $stored): void {
            $user->forceFill([
                'avatar_path' => $stored['path'],
                'avatar_original_name' => $stored['original_name'],
                'avatar_mime_type' => $stored['mime_type'],
                'avatar_size_bytes' => $stored['size_bytes'],
            ])->save();
        });

        // The OLD file is deleted only after the new row is safely saved —
        // never before. Deleting first and having the save fail would leave
        // the account with neither an old photo nor a new one.
        $this->storage->delete($previousPath);

        $this->audit->record(
            action: 'profile.avatar_uploaded',
            actor: $user,
            subject: $user,
            changes: ['after' => ['avatar_original_name' => $stored['original_name']]],
            description: 'Uploaded a profile photo.',
        );

        return $user;
    }
}
