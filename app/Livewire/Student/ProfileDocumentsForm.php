<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Actions\Profile\RemoveAadhaarDocument;
use App\Actions\Profile\RemoveAvatar;
use App\Actions\Profile\UploadAadhaarDocument;
use App\Actions\Profile\UploadAvatar;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Upload Documents (FR-STU-12-adjacent, NFR-DATA-01) — a passport-style photo
 * and an Aadhaar card scan.
 *
 * A SEPARATE COMPONENT FROM ProfileForm, DELIBERATELY. Every other field on
 * the profile page is typed text that only takes effect on an explicit "Save
 * details" click. A file upload does not fit that shape — Livewire uploads
 * the file to temporary storage the moment it is chosen, so there is no
 * meaningful "unsaved" state to hold in a shared form, and bundling it with
 * fifteen unrelated text fields would mean one failed upload blocking a name
 * change, or a name typo re-triggering file validation. Each upload commits
 * on selection instead — see uploadPhoto()/uploadAadhaarDocument().
 *
 * uploadPhoto() and removePhoto() dispatch 'profile-photo-updated' — heard by
 * App\Livewire\Student\ProfileIdentity, the read-only card elsewhere on the
 * profile page that shows this same photo. That card is a separate Livewire
 * component and would otherwise never learn the photo changed; the surrounding
 * page is plain Blade and renders once. Nothing listens for an Aadhaar-card
 * change — nothing else on the page shows it.
 *
 * WHAT THIS DOES NOT DO: an image-cropping step (the reference design's
 * "Crop" button) and an administrator review/verification screen for these
 * documents. Both were explicitly scoped out when this was built. A file is
 * accepted as uploaded; cropping, if wanted later, is a client-side feature
 * added on top of this, not a change to how the file is stored.
 */
final class ProfileDocumentsForm extends Component
{
    use WithFileUploads;

    /**
     * Never persisted directly — see uploadPhoto(). Cleared back to null
     * after every upload attempt, successful or not, so a component that
     * re-renders never re-submits a file the user already dealt with.
     */
    public mixed $photo = null;

    public mixed $aadhaarDocument = null;

    /**
     * Livewire calls updated{Property}() automatically the moment a bound
     * file input changes — this is what makes the upload commit on selection
     * with no separate "Upload" button, matching the reference design.
     */
    public function updatedPhoto(): void
    {
        $this->uploadPhoto();
    }

    public function updatedAadhaarDocument(): void
    {
        $this->uploadAadhaarDocument();
    }

    /**
     * Passport-style photo: an image only, up to 5MB.
     *
     * The size ceilings here (5MB photo, 10MB Aadhaar) sit well under
     * Livewire's temporary-upload cap (config/livewire.php) but ABOVE PHP's
     * stock upload_max_filesize of 2M. Every documented environment already
     * raises it (planning.md §20: upload_max_filesize/post_max_size=2048M,
     * for course video) — but on a box that skipped that step, a file this
     * rule would accept is refused by PHP before Laravel ever sees it.
     */
    public function uploadPhoto(): void
    {
        $this->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
        ], attributes: ['photo' => 'photo']);

        app(UploadAvatar::class)->handle($this->user(), $this->photo);

        $this->photo = null;

        // Heard by ProfileIdentity — the read-only card elsewhere on this
        // page that shows this same photo and otherwise never re-renders.
        $this->dispatch('profile-photo-updated');

        session()->flash('status', 'Your photo has been uploaded.');
    }

    public function removePhoto(): void
    {
        app(RemoveAvatar::class)->handle($this->user());

        $this->dispatch('profile-photo-updated');

        session()->flash('status', 'Your photo has been removed.');
    }

    /**
     * Aadhaar card: an image or a PDF scan, up to 10MB.
     */
    public function uploadAadhaarDocument(): void
    {
        $this->validate([
            'aadhaarDocument' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:10240'],
        ], attributes: ['aadhaarDocument' => 'Aadhaar card']);

        app(UploadAadhaarDocument::class)->handle($this->user(), $this->aadhaarDocument);

        $this->aadhaarDocument = null;

        session()->flash('status', 'Your Aadhaar card has been uploaded.');
    }

    public function removeAadhaarDocument(): void
    {
        app(RemoveAadhaarDocument::class)->handle($this->user());

        session()->flash('status', 'Your Aadhaar card has been removed.');
    }

    public function render(): View
    {
        $user = $this->user();
        $attributes = $user->getAttributes();

        return view('livewire.student.profile-documents-form', [
            'avatarOriginalName' => $attributes['avatar_original_name'] ?? null,
            'avatarSizeBytes' => $attributes['avatar_size_bytes'] ?? null,
            'aadhaarDocumentOriginalName' => $attributes['aadhaar_document_original_name'] ?? null,
            'aadhaarDocumentMimeType' => $attributes['aadhaar_document_mime_type'] ?? null,
            'aadhaarDocumentSizeBytes' => $attributes['aadhaar_document_size_bytes'] ?? null,
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
