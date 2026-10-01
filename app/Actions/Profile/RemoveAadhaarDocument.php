<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Profile\ProfileDocumentStorage;
use Illuminate\Support\Facades\DB;

/**
 * Remove a learner's stored Aadhaar card scan. A no-op if none is stored.
 */
final class RemoveAadhaarDocument
{
    public function __construct(
        private readonly ProfileDocumentStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user): User
    {
        $path = $user->getAttributes()['aadhaar_document_path'] ?? null;

        if ($path === null) {
            return $user;
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'aadhaar_document_path' => null,
                'aadhaar_document_original_name' => null,
                'aadhaar_document_mime_type' => null,
                'aadhaar_document_size_bytes' => null,
            ])->save();
        });

        $this->storage->delete($path);

        $this->audit->record(
            action: 'profile.aadhaar_document_removed',
            actor: $user,
            subject: $user,
            description: 'Removed their Aadhaar card scan.',
        );

        return $user;
    }
}
