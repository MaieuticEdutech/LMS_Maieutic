<?php

declare(strict_types=1);

namespace App\Services\Profile;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores and retrieves a learner's own uploaded documents — a profile photo
 * and an Aadhaar card scan — as ciphertext on the `profile_documents` disk
 * (config/filesystems.php), which is never served directly.
 *
 * A DELIBERATELY SEPARATE, SMALLER SYSTEM FROM MediaStorageService. Course
 * content (video, lesson PDFs) goes through `media_files` and MediaPurpose,
 * which carries its own config surface and its own owner. This exists
 * because staying out of that system, rather than extending it, was the
 * explicit choice when profile-document upload was scoped.
 *
 * ENCRYPTS THE FILE'S BYTES, NOT ITS NAME. The path returned by store() is a
 * random token — unguessable, but not treated as a secret in itself, because
 * the disk is never web-served and the caller (ProfileDocumentController)
 * checks ownership before ever reading it back. What actually protects the
 * document is that nothing on disk is readable without APP_KEY.
 */
final class ProfileDocumentStorage
{
    private const DISK = 'profile_documents';

    /**
     * Encrypt an uploaded file and write it to disk.
     *
     * @return array{path: string, original_name: string, mime_type: string, size_bytes: int}
     */
    public function store(UploadedFile $file, string $directory): array
    {
        // getMimeType() reads the file's actual content via finfo, not the
        // client-supplied header — the same distinction FileValidationService
        // draws for course-content uploads (a browser controls the header, not
        // the bytes).
        $mimeType = (string) $file->getMimeType();
        $originalName = (string) $file->getClientOriginalName();
        $sizeBytes = (int) $file->getSize();

        // The extension is cosmetic — nothing here is served with it, since
        // every read goes through a controller that sets Content-Type from
        // the stored mime_type — but keeping it makes a directory listing on
        // the disk legible to whoever operates it.
        $extension = $file->getClientOriginalExtension();
        $filename = Str::ulid().($extension !== '' ? '.'.$extension : '').'.enc';
        $path = trim($directory, '/').'/'.$filename;

        $ciphertext = Crypt::encryptString((string) file_get_contents($file->getRealPath()));

        Storage::disk(self::DISK)->put($path, $ciphertext);

        return [
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ];
    }

    /**
     * Decrypt a stored document's bytes for serving.
     *
     * Lets Crypt's DecryptException propagate: a document that cannot be
     * decrypted (APP_KEY rotated without re-encrypting) is a real failure the
     * caller must handle, not something this method can paper over the way
     * User::identityNumber() does for a display-only masked number.
     */
    public function retrieve(string $path): string
    {
        $ciphertext = Storage::disk(self::DISK)->get($path);

        return Crypt::decryptString((string) $ciphertext);
    }

    /**
     * Remove a stored document. Silently does nothing if the path is already
     * gone — the caller's job (an Action) is to make sure a column ends up
     * null, not to guarantee the file existed.
     */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }
}
