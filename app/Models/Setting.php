<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single organisation-level configuration value (FR-SYS-01, FR-ADM-16).
 *
 * Read through App\Services\Settings\SettingsRepository, never directly —
 * the repository caches, casts and provides defaults, and is the seam that
 * becomes organisation-scoped in V2 (architecture.md §24.2).
 *
 * @property int $id
 * @property string $group
 * @property string $key
 * @property mixed $value
 * @property string $type
 * @property bool $is_public
 */
class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'is_public',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // JSONB round-trips scalars, lists and structures uniformly, so a
            // setting's shape can change without a migration.
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
