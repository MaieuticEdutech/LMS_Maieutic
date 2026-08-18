<?php

declare(strict_types=1);

namespace App\Actions\Certificate;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Award a certificate for a completed enrolment (design handoff §7).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * THE SOLE WRITER OF `certificates`. NOTHING ELSE CREATES ONE.
 *
 * Same shape and the same reason as GrantEnrollment (ADR-006): a certificate is
 * a claim the organisation makes about a person, so there is exactly one place
 * that decides it is warranted, and that place audits the decision.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * IDEMPOTENT, TWICE OVER.
 *
 * A course can be recalculated, a queue job retried, a lesson republished. None
 * of those may produce a second certificate, so:
 *
 *   1. an existing certificate for the enrolment is returned unchanged;
 *   2. if two workers race past that check at once, the UNIQUE index on
 *      enrollment_id rejects the loser and it re-reads the winner's row.
 *
 * The check alone is not enough — it is a read followed by a write, which is
 * the definition of a race. The index is what makes the rule true.
 *
 * REFUSES AN INCOMPLETE ENROLMENT. Completion is the enrollment's own
 * `completed_at`, not a percentage: a course with a final test reads 100% while
 * the test is still outstanding, and awarding on the figure would hand out a
 * credential nobody had earned (ADR-008, AC-31).
 */
final class IssueCertificate
{
    /**
     * The characters a certificate number may contain.
     *
     * Declared once, so the generator, the factory and the format check cannot
     * drift — the first version of this had the alphabet written out twice and
     * they already disagreed about whether `8` was allowed.
     */
    public const ALPHABET = 'ACDEFGHJKLMNPQRTUVWXYZ234679';

    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Enrollment $enrollment): Certificate
    {
        $existing = Certificate::query()->where('enrollment_id', $enrollment->getKey())->first();

        if ($existing instanceof Certificate) {
            return $existing;
        }

        if ($enrollment->completed_at === null) {
            throw new RuntimeException(
                "Enrollment #{$enrollment->getKey()} has not completed its course, so no certificate is due.",
            );
        }

        $enrollment->loadMissing(['user', 'course']);

        $user = $enrollment->user ?? throw new RuntimeException(
            "Enrollment #{$enrollment->getKey()} has no user — the FK constraint should make this impossible.",
        );

        $course = $enrollment->course ?? throw new RuntimeException(
            "Enrollment #{$enrollment->getKey()} has no course — the FK constraint should make this impossible.",
        );

        try {
            $certificate = DB::transaction(function () use ($enrollment, $user, $course): Certificate {
                $certificate = new Certificate;

                $certificate->fill([
                    'enrollment_id' => $enrollment->getKey(),
                    'user_id' => $user->getKey(),
                    'course_id' => $course->getKey(),
                    // Snapshots, taken now. See the model docblock.
                    'recipient_name' => $user->certificateName(),
                    'course_title' => $course->title,
                    // The award date is the completion, not the moment this row
                    // happened to be written — a backfill must not print today.
                    'issued_at' => $enrollment->completed_at,
                ]);

                // Not fillable: a caller able to choose the number could collide
                // with, or impersonate, an award already made.
                $certificate->number = $this->generateNumber();
                $certificate->save();

                return $certificate;
            });
        } catch (UniqueConstraintViolationException) {
            /*
             * Lost the race. The winner's row is the correct answer — re-read it
             * rather than retrying, because retrying would just lose again.
             */
            return Certificate::query()
                ->where('enrollment_id', $enrollment->getKey())
                ->firstOrFail();
        }

        $this->audit->record(
            action: 'certificate.issued',
            actor: null,
            subject: $certificate,
            changes: ['after' => ['number' => $certificate->number, 'course_id' => $course->getKey()]],
            description: "Issued certificate {$certificate->number} to {$user->email} for \"{$course->title}\".",
        );

        return $certificate;
    }

    /**
     * MAI-CERT-XXXX-XXXX, from a cryptographically secure source.
     *
     * Random rather than sequential so the identifier cannot be walked: a
     * verification page that accepts MAI-CERT-0000-0001 would otherwise let
     * anyone enumerate every award the organisation has ever made.
     *
     * BOTH HALVES OF EVERY CONFUSABLE PAIR ARE EXCLUDED: O and 0, I and 1, S
     * and 5, B and 8. Dropping only the letter would be enough for a reader
     * decoding the string, but not for someone READING IT ALOUD down a phone —
     * "eight" and "B" sound nothing alike, but a person transcribing a
     * handwritten note has neither advantage. The whole point of this string is
     * that a human retypes it into a verification box.
     *
     * 28 characters, 8 of them: ~3.8e11 combinations.
     */
    private function generateNumber(): string
    {
        $alphabet = self::ALPHABET;

        do {
            $block = static fn (): string => collect(range(1, 4))
                ->map(static fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->join('');

            $number = 'MAI-CERT-'.$block().'-'.$block();

            // A collision is vanishingly unlikely at this size — but "unlikely"
            // is not "impossible", and the unique index would otherwise turn one
            // into a failed award.
        } while (Certificate::query()->where('number', $number)->exists());

        return $number;
    }

    /**
     * Kept for callers that want the ID format without an award (tests, docs).
     */
    public static function looksLikeCertificateNumber(string $candidate): bool
    {
        $class = '['.self::ALPHABET.']';

        return Str::isMatch("/^MAI-CERT-{$class}{4}-{$class}{4}$/", $candidate);
    }
}
