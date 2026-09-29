<?php

declare(strict_types=1);

use App\Livewire\Student\ProfileDocumentsForm;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Upload Documents — a profile photo and an Aadhaar card scan
|--------------------------------------------------------------------------
|
| Beyond "does it save", this pins down the things that make this a document
| store rather than a plain file upload: the bytes on disk are ciphertext,
| never served except through the authenticated controller, an Aadhaar
| upload's filename and path never reach the audit log, and a replaced or
| removed file is actually deleted rather than orphaned.
|
| A real image and real PDF magic bytes throughout — the same reasoning
| tests/Feature/Admin/Courses/MediaUploaderTest.php documents: Laravel's
| `mimes:` rule sniffs actual content, and a garbage-bytes fake() file would
| never exercise that.
*/

function realPdfUpload(string $filename = 'aadhaar.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $filename,
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n",
    );
}

beforeEach(function (): void {
    Storage::fake('profile_documents');

    $this->student = User::factory()->student()->create();
    $this->actingAs($this->student);
});

/*
| ═══════════════ PHOTO ═══════════════
*/
it('uploads a photo', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('photo', UploadedFile::fake()->image('me.jpg', 200, 200)->size(500))
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->getAttributes()['avatar_path'] ?? null)->not->toBeNull()
        ->and($fresh->avatar_original_name)->toBe('me.jpg')
        ->and($fresh->avatar_mime_type)->toBe('image/jpeg')
        ->and($fresh->avatar_size_bytes)->toBeGreaterThan(0);

    Storage::disk('profile_documents')->assertExists($fresh->getAttributes()['avatar_path']);
});

it('rejects a photo over 5MB', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('photo', UploadedFile::fake()->image('me.jpg')->size(5121))
        ->assertHasErrors(['photo']);

    expect($this->student->refresh()->getAttributes()['avatar_path'] ?? null)->toBeNull();
});

it('rejects a non-image file as a photo', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('photo', realPdfUpload('me.pdf'))
        ->assertHasErrors(['photo']);
});

it('replaces a photo and deletes the old file', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('photo', UploadedFile::fake()->image('first.jpg', 100, 100)->size(200));

    $firstPath = $this->student->refresh()->getAttributes()['avatar_path'];

    Livewire::test(ProfileDocumentsForm::class)
        ->set('photo', UploadedFile::fake()->image('second.jpg', 100, 100)->size(200))
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->avatar_original_name)->toBe('second.jpg')
        ->and($fresh->getAttributes()['avatar_path'])->not->toBe($firstPath);

    Storage::disk('profile_documents')->assertMissing($firstPath);
});

it('removes a photo', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('photo', UploadedFile::fake()->image('me.jpg', 100, 100)->size(200));

    $path = (string) $this->student->refresh()->getAttributes()['avatar_path'];

    Livewire::test(ProfileDocumentsForm::class)->call('removePhoto');

    $fresh = $this->student->refresh();

    expect($fresh->getAttributes()['avatar_path'] ?? null)->toBeNull()
        ->and($fresh->avatar_original_name)->toBeNull();

    Storage::disk('profile_documents')->assertMissing($path);
});

/*
| ═══════════════ AADHAAR CARD ═══════════════
*/
it('uploads an Aadhaar card as an image', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('aadhaarDocument', UploadedFile::fake()->image('aadhaar.jpg', 300, 200)->size(800))
        ->assertHasNoErrors();

    $fresh = $this->student->refresh();

    expect($fresh->aadhaar_document_original_name)->toBe('aadhaar.jpg')
        ->and($fresh->aadhaar_document_mime_type)->toBe('image/jpeg');
});

it('uploads an Aadhaar card as a PDF', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('aadhaarDocument', realPdfUpload())
        ->assertHasNoErrors();

    expect($this->student->refresh()->aadhaar_document_mime_type)->toBe('application/pdf');
});

it('rejects an Aadhaar card over 10MB', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('aadhaarDocument', UploadedFile::fake()->image('aadhaar.jpg')->size(10241))
        ->assertHasErrors(['aadhaarDocument']);
});

it('rejects a disallowed file type for the Aadhaar card', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('aadhaarDocument', UploadedFile::fake()->create('aadhaar.exe', 10, 'application/x-msdownload'))
        ->assertHasErrors(['aadhaarDocument']);
});

it('removes an Aadhaar card', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('aadhaarDocument', realPdfUpload());

    $path = (string) $this->student->refresh()->aadhaar_document_path;

    Livewire::test(ProfileDocumentsForm::class)->call('removeAadhaarDocument');

    $fresh = $this->student->refresh();

    expect($fresh->aadhaar_document_path)->toBeNull()
        ->and($fresh->aadhaar_document_original_name)->toBeNull();

    Storage::disk('profile_documents')->assertMissing($path);
});

/*
| ═══════════════ ENCRYPTED AT REST ═══════════════
*/
it('stores the photo encrypted, never as the original bytes', function (): void {
    $upload = UploadedFile::fake()->image('me.jpg', 50, 50)->size(100);
    $originalBytes = file_get_contents($upload->getRealPath());

    Livewire::test(ProfileDocumentsForm::class)->set('photo', $upload);

    $path = (string) $this->student->refresh()->getAttributes()['avatar_path'];
    $stored = Storage::disk('profile_documents')->get($path);

    expect($stored)->not->toBe($originalBytes)
        // A real JPEG starts with the bytes FF D8 — ciphertext should not.
        ->and(str_starts_with((string) $stored, "\xFF\xD8"))->toBeFalse();
});

it('stores the Aadhaar card encrypted, never in readable form', function (): void {
    Livewire::test(ProfileDocumentsForm::class)->set('aadhaarDocument', realPdfUpload());

    $path = (string) $this->student->refresh()->aadhaar_document_path;
    $stored = (string) Storage::disk('profile_documents')->get($path);

    expect($stored)->not->toContain('%PDF');
});

/*
| ═══════════════ SERVING — SELF-SERVICE ONLY, DECRYPTED CORRECTLY ═══════════════
*/
it('redirects an unauthenticated request to sign in', function (): void {
    auth()->logout();

    $this->get('/profile/photo')->assertRedirect(route('login'));
});

it('answers 404 for a user with no photo uploaded', function (): void {
    $this->get('/profile/photo')->assertNotFound();
});

it('serves the exact bytes of an uploaded photo, decrypted', function (): void {
    $upload = UploadedFile::fake()->image('me.jpg', 40, 40)->size(60);
    $originalBytes = (string) file_get_contents($upload->getRealPath());

    Livewire::test(ProfileDocumentsForm::class)->set('photo', $upload);

    $response = $this->get('/profile/photo');

    $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    // Directive order is the framework's own normalisation, not something
    // this controller controls — assert on the directives, not their order.
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
        ->and($response->getContent())->toBe($originalBytes);
});

it('serves an uploaded Aadhaar card, decrypted, inline rather than as a download', function (): void {
    Livewire::test(ProfileDocumentsForm::class)->set('aadhaarDocument', realPdfUpload());

    $response = $this->get('/profile/aadhaar-document');

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->and($response->getContent())->toContain('%PDF');
});

it('never serves one account\'s document to another', function (): void {
    Livewire::test(ProfileDocumentsForm::class)->set('photo', UploadedFile::fake()->image('mine.jpg', 40, 40)->size(60));

    $other = User::factory()->student()->create();
    $this->actingAs($other);

    // No route accepts a user id at all — every request is "my own document",
    // so a second account with nothing uploaded gets exactly the same 404 a
    // first-time visitor would.
    $this->get('/profile/photo')->assertNotFound();
});

/*
| ═══════════════ AUDIT LOG ═══════════════
*/
it('records a photo upload in the audit log with the filename visible', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('photo', UploadedFile::fake()->image('graduation-photo.jpg', 100, 100)->size(200));

    $entry = AuditLog::query()->where('action', 'profile.avatar_uploaded')->latest('id')->firstOrFail();

    expect(json_encode($entry->getAttributes()))->toContain('graduation-photo.jpg');
});

it('records an Aadhaar upload in the audit log but redacts the filename and path', function (): void {
    Livewire::test(ProfileDocumentsForm::class)->set('aadhaarDocument', realPdfUpload('my-aadhaar-card.pdf'));

    $entry = AuditLog::query()->where('action', 'profile.aadhaar_document_uploaded')->latest('id')->firstOrFail();
    $logged = json_encode($entry->getAttributes());

    expect($logged)->not->toContain('my-aadhaar-card.pdf')
        ->and($logged)->toContain('[redacted]');
});

it('records removal of each document in the audit log', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('photo', UploadedFile::fake()->image('me.jpg', 40, 40)->size(60))
        ->call('removePhoto');

    Livewire::test(ProfileDocumentsForm::class)
        ->set('aadhaarDocument', realPdfUpload())
        ->call('removeAadhaarDocument');

    expect(AuditLog::query()->where('action', 'profile.avatar_removed')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'profile.aadhaar_document_removed')->exists())->toBeTrue();
});

/*
| ═══════════════ THE SCREEN ═══════════════
*/
it('shows the upload documents section with no files yet', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->assertSee('Upload Documents')
        ->assertSee('Passport size/style photo')
        ->assertSee('Aadhaar card')
        ->assertDontSee('Preview')
        ->assertDontSee('Remove');
});

it('shows a preview link and remove button once a document is uploaded', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('photo', UploadedFile::fake()->image('me.jpg', 40, 40)->size(60))
        ->assertSee('Preview')
        ->assertSee('Remove')
        ->assertSee('me.jpg');
});

it('offers a drag-and-drop target for each upload', function (): void {
    // The visible affordance for x-file-drop-zone — the field itself is
    // still the same wire:model input every other test here already proves
    // works, so this only checks the drop-zone hint is actually on the page.
    Livewire::test(ProfileDocumentsForm::class)
        ->assertSeeHtml('drag and drop a file here');
});

/*
| ═══════════════ THE IDENTITY CARD LEARNS ABOUT A NEW PHOTO ═══════════════
|
| profile-photo-updated is the only channel App\Livewire\Student\ProfileIdentity
| — a separate component on the same page — has for finding out the photo
| changed. If this stops firing, that card silently goes stale.
*/
it('dispatches profile-photo-updated after uploading a photo', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('photo', UploadedFile::fake()->image('me.jpg', 40, 40)->size(60))
        ->assertDispatched('profile-photo-updated');
});

it('dispatches profile-photo-updated after removing a photo', function (): void {
    Livewire::test(ProfileDocumentsForm::class)
        ->set('photo', UploadedFile::fake()->image('me.jpg', 40, 40)->size(60));

    Livewire::test(ProfileDocumentsForm::class)
        ->call('removePhoto')
        ->assertDispatched('profile-photo-updated');
});

it('does not dispatch profile-photo-updated for the Aadhaar card', function (): void {
    // Nothing else on the page shows the Aadhaar document, so there is
    // nothing for this event to keep in sync there.
    Livewire::test(ProfileDocumentsForm::class)
        ->set('aadhaarDocument', realPdfUpload())
        ->assertNotDispatched('profile-photo-updated');
});
