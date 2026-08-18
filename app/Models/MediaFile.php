<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaPurpose;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * One uploaded file — video, PDF, presentation, resource or thumbnail
 * (ADR-003, FR-FILE-01…14).
 *
 * ONE TABLE FOR EVERY UPLOADED BYTE. There is no `videos`, `notes`,
 * `presentations` or `resources` model. That means ONE upload pipeline to
 * secure and ONE policy to get right — which matters because file access is
 * where this system is most likely to leak.
 *
 * @property int $id
 * @property string $ulid
 * @property string $attachable_type
 * @property int $attachable_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property string $extension
 * @property int $size_bytes
 * @property string|null $checksum_sha256
 * @property MediaPurpose $purpose
 * @property bool $is_downloadable
 * @property int $position
 * @property int|null $uploaded_by
 */
class MediaFile extends Model
{
    /** @use HasFactory<\Database\Factories\MediaFileFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * `disk` and `path` are absent (NFR-SEC-07).
     *
     * They are written ONLY by MediaStorageService using a path from
     * MediaPathResolver (rule S-2, FR-FILE-11). If they were fillable, a
     * request could point a media record at an arbitrary file on the disk —
     * including one belonging to another course.
     *
     * @var list<string>
     */
    protected $fillable = [
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum_sha256',
        'purpose',
        'is_downloadable',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => MediaPurpose::class,
            'size_bytes' => 'integer',
            'is_downloadable' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * URLs expose the ULID, never the integer id.
     *
     * Sequential ids would let anyone enumerate the catalogue's media by
     * counting. This is the difference between "you need a link" and "you
     * need to guess a 26-character identifier".
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * The Lesson, Course, Question or User this file belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    /**
     * Resolve the course this file ultimately belongs to.
     *
     * EVERY access decision starts here (FR-FILE-06): resolve the owning
     * course, then ask EnrollmentAccessService whether this user may reach it.
     * Returning null means the owner could not be resolved — which
     * MediaFilePolicy must treat as DENY, never as "no restriction".
     */
    public function resolveCourse(): ?Course
    {
        $attachable = $this->attachable;

        return match (true) {
            $attachable instanceof Course => $attachable,
            $attachable instanceof Lesson => $attachable->course(),
            default => null,
        };
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfPurpose(Builder $query, MediaPurpose $purpose): void
    {
        $query->where('purpose', $purpose);
    }

    /**
     * Files on the private content disk — everything except thumbnails.
     *
     * @param  Builder<self>  $query
     */
    public function scopeProtected(Builder $query): void
    {
        $query->where('purpose', '!=', MediaPurpose::Thumbnail);
    }

    /**
     * Is this file streamed (video) rather than downloaded?
     */
    public function isStreamed(): bool
    {
        return $this->purpose->isStreamed();
    }

    /**
     * A URL an <img> can use, or null.
     *
     * ONLY THUMBNAILS QUALIFY, AND THAT IS A SECURITY BOUNDARY RATHER THAN A
     * CONVENIENCE. Thumbnails are the one PUBLIC medium in the system; every
     * other upload lives on the private content disk and is reached through an
     * authorised controller. Returning a plain URL for a lesson video would be
     * handing out an unauthenticated link to protected content, so the purpose
     * is checked rather than the mime type alone.
     *
     * Exists so the admin uploader can SHOW a thumbnail rather than describe
     * it. Uploading an image and getting back a filename and a file icon gives
     * an author no way to tell a correct upload from a broken one — which is
     * how a missing storage symlink went unnoticed.
     */
    public function previewUrl(): ?string
    {
        if ($this->purpose !== MediaPurpose::Thumbnail) {
            return null;
        }

        if (! str_starts_with($this->mime_type, 'image/')) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size_bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }
}
