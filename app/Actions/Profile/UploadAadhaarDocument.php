<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Profile\ProfileDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Store a learner's Aadhaar card scan, replacing any existing one.
 *
 * Same shape as UploadAvatar — see its docblock for why forceFill() is used
 * rather than fill(). The audit entry AuditLogger writes for this action has
 * both its path and its original filename redacted (see AuditLogger::REDACTED);
 * only the fact that an Aadhaar document was uploaded is recorded.
 */
final class UploadAadhaarDocument
{
    public function __construct(
        private readonly ProfileDocumentStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $user, UploadedFile $file): User
    {
        $previousPath = $user->getAttributes()['aadhaar_document_path'] ?? null;

        $stored = $this->storage->store($file, 'aadhaar-documents/'.$user->getKey());

        DB::transaction(function () use ($user, $stored): void {
            $user->forceFill([
                'aadhaar_document_path' => $stored['path'],
                'aadhaar_document_original_name' => $stored['original_name'],
                'aadhaar_document_mime_type' => $stored['mime_type'],
                'aadhaar_document_size_bytes' => $stored['size_bytes'],
            ])->save();
        });

        $this->storage->delete($previousPath);

        $this->audit->record(
            action: 'profile.aadhaar_document_uploaded',
            actor: $user,
            subject: $user,
            changes: ['after' => ['aadhaar_document_original_name' => $stored['original_name']]],
            description: 'Uploaded an Aadhaar card scan.',
        );

        return $user;
    }
}
