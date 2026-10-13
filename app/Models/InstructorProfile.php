<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaPurpose;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

/**
 * Instructor-specific profile data, kept out of `users` so the identity table
 * stays role-neutral (architecture.md §6.4).
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $headline
 * @property string|null $bio
 * @property list<string>|null $expertise
 * @property array<string, string>|null $links
 */
class InstructorProfile extends Model
{
    /** @use HasFactory<\Database\Factories\InstructorProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'headline',
        'bio',
        'expertise',
        'links',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expertise' => 'array',
            'links' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The instructor's public profile photo, shown on course cards and pages.
     *
     * Stored through the same media pipeline as course thumbnails (purpose
     * `thumbnail`, public disk), so it gets the same type sniffing and
     * extension rules. NOT the student profile photo: those are private and
     * encrypted (ProfileDocumentStorage) and must never be served publicly.
     *
     * @return MorphMany<MediaFile, $this>
     */
    public function photo(): MorphMany
    {
        return $this->morphMany(MediaFile::class, 'attachable')
            ->where('purpose', MediaPurpose::Thumbnail)
            ->latest('id');
    }

    /**
     * Public URL of the photo, or null for the initials fallback.
     *
     * Reads only an already-loaded relation, so a page listing many
     * instructors never fires a query per card (preventLazyLoading).
     */
    public function photoUrl(): ?string
    {
        if (! $this->relationLoaded('photo')) {
            return null;
        }

        $media = $this->photo->first();

        return $media !== null ? Storage::disk($media->disk)->url($media->path) : null;
    }
}
