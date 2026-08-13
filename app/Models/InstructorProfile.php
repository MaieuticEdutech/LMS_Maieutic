<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
