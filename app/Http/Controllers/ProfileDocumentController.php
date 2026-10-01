<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Profile\ProfileDocumentStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a learner's own uploaded profile photo or Aadhaar card scan.
 *
 * SELF-SERVICE ONLY. Every route this controller answers reads
 * `Auth::user()` — there is no user-id route parameter, so there is nothing
 * for one account to substitute another's into. An administrator reviewing a
 * student's identity documents is a real need this does not cover; it was
 * not asked for when this was built and is a separate piece of work with its
 * own authorisation question, not an extension of this controller.
 *
 * The disk these documents live on has `serve => false`
 * (config/filesystems.php's `profile_documents`) specifically so this
 * controller — auth-gated by routes/student.php's ['auth', 'active']
 * middleware — is the only way to reach one.
 */
final class ProfileDocumentController extends Controller
{
    public function __construct(private readonly ProfileDocumentStorage $storage) {}

    public function avatar(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return $this->stream(
            $user->getAttributes()['avatar_path'] ?? null,
            $user->getAttributes()['avatar_mime_type'] ?? null,
            $user->getAttributes()['avatar_original_name'] ?? null,
        );
    }

    public function aadhaarDocument(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return $this->stream(
            $user->getAttributes()['aadhaar_document_path'] ?? null,
            $user->getAttributes()['aadhaar_document_mime_type'] ?? null,
            $user->getAttributes()['aadhaar_document_original_name'] ?? null,
        );
    }

    private function stream(?string $path, ?string $mimeType, ?string $originalName): Response
    {
        if ($path === null) {
            abort(404);
        }

        return response($this->storage->retrieve($path), Response::HTTP_OK, [
            'Content-Type' => $mimeType ?? 'application/octet-stream',

            // inline, not attachment: the point is to preview the document in
            // the browser (an <img> for the photo, the browser's own PDF
            // viewer for the Aadhaar scan), not to trigger a download.
            'Content-Disposition' => 'inline; filename="'.($originalName ?? 'document').'"',

            // Never a shared cache, and never stored: this is a private
            // document read fresh from encrypted storage on every request.
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
