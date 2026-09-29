<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A structurally valid Aadhaar number: 12 digits, not starting with 0 or 1,
 * with a correct Verhoeff check digit.
 *
 * STRUCTURE ONLY. Passing this rule means the number is well formed — it says
 * nothing about whether UIDAI issued it, or to whom. Nothing in this system may
 * treat a stored Aadhaar number as verified identity.
 *
 * Expects the value already normalised to bare digits; the caller strips the
 * spaces people type ("2341 2341 2346") before validating.
 */
final class Aadhaar implements ValidationRule
{
    /** Verhoeff multiplication table (dihedral group D5). */
    private const D = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
        [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
        [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
        [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
        [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
        [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
        [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
        [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
        [9, 8, 7, 6, 5, 4, 3, 2, 1, 0],
    ];

    /** Verhoeff permutation table. */
    private const P = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
        [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
        [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
        [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
        [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
        [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
        [7, 0, 4, 6, 9, 1, 3, 2, 5, 8],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[2-9][0-9]{11}$/', $value) !== 1) {
            $fail('The :attribute must be 12 digits and cannot start with 0 or 1.');

            return;
        }

        if (! self::checksumIsValid($value)) {
            // Most often a single mistyped or transposed digit — exactly the
            // errors Verhoeff exists to catch.
            $fail('The :attribute is not valid. Please check it for a mistyped digit.');
        }
    }

    private static function checksumIsValid(string $digits): bool
    {
        $check = 0;

        foreach (array_reverse(str_split($digits)) as $position => $digit) {
            $check = self::D[$check][self::P[$position % 8][(int) $digit]];
        }

        return $check === 0;
    }
}
