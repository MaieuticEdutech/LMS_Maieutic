<?php

declare(strict_types=1);

namespace App\Actions\Assessment;

use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Add many questions to an assessment in one go (design: bulk question import).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * DELEGATES EVERY WRITE TO CreateQuestion. IT DOES NOT TOUCH `questions`.
 *
 * CreateQuestion already owns the rules that matter: the answer key is checked
 * through QuestionTypeRegistry so each type brings its own "exactly one
 * correct" / "at least one correct" rule (FR-ASMT-07), bodies are sanitised,
 * positions are assigned, the counters are refreshed and the change is
 * audited.
 *
 * An importer that inserted rows directly would be a second writer that has to
 * remember all of that — and would drift the first time any of those rules
 * changed. Bulk import is a loop with a transaction around it, not a parallel
 * implementation.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * ALL OR NOTHING. One bad row rolls the whole import back rather than leaving
 * an assessment half-populated, because "which of these 40 landed?" is a far
 * worse question to be asked than "row 12 was rejected, fix it and re-upload".
 * The review screen exists so this is rare.
 */
final class ImportQuestions
{
    public function __construct(
        private readonly CreateQuestion $create,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{
     *     row: int,
     *     body: string,
     *     explanation: string|null,
     *     options: list<array{body: string, is_correct: bool}>,
     *     type: QuestionType,
     *     marks: int|float|string,
     * }>  $questions
     * @return int how many were created
     *
     * @throws InvalidArgumentException naming the row that failed
     */
    public function handle(Assessment $assessment, array $questions, User $actor): int
    {
        if ($questions === []) {
            throw new InvalidArgumentException('There are no questions to import.');
        }

        DB::transaction(function () use ($assessment, $questions, $actor): void {
            foreach ($questions as $question) {
                try {
                    $this->create->handle($assessment, [
                        'type' => $question['type'],
                        'body' => $question['body'],
                        'explanation' => $question['explanation'],
                        'marks' => $question['marks'],
                        /*
                         * A short-answer question has no options; the registry
                         * ignores them for types that do not require them, so
                         * they are passed through unconditionally rather than
                         * branching on the type here — the same reason
                         * CreateQuestion does not branch on it either.
                         */
                        'options' => $question['options'],
                    ], $actor);
                } catch (InvalidArgumentException $e) {
                    /*
                     * Re-thrown with the spreadsheet row number attached. The
                     * registry's message says what is wrong with the answer
                     * key; without the row, the author has to find it among
                     * forty otherwise identical-looking questions.
                     */
                    throw new InvalidArgumentException(
                        sprintf('Row %d could not be imported: %s Nothing was imported.', $question['row'], $e->getMessage()),
                        previous: $e,
                    );
                }
            }
        });

        /*
         * Audited as ONE event, not one per question. Forty separate
         * "question.created" entries from a single upload buries the audit log
         * and tells a reader less than a single line naming the file's size.
         * CreateQuestion still records each one; this marks the batch.
         */
        $this->audit->record(
            action: 'question.imported',
            actor: $actor,
            subject: $assessment,
            changes: ['after' => ['count' => count($questions)]],
            description: sprintf('Imported %d questions into "%s".', count($questions), $assessment->title),
        );

        return count($questions);
    }
}
