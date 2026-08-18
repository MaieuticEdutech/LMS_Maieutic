<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Exceptions\SpreadsheetException;
use App\Support\Spreadsheet\XlsxReader;

/**
 * Turn an uploaded question spreadsheet into reviewable candidate questions.
 *
 * The format, as supplied by the customer:
 *
 *     Question | Option A | Option B | Option C | Option D | Answer | Explanation
 *
 * ═════════════════════════════════════════════════════════════════════════
 * COLUMNS ARE MATCHED BY HEADER NAME, NEVER BY POSITION.
 *
 * Someone will eventually insert a column, reorder two, or add "Topic" in the
 * middle. Reading positionally would then quietly load an option into the
 * answer field — an import that appears to succeed and marks students wrong on
 * correct answers. Matching by name turns that into "I can't find a column
 * called Answer", which is a question somebody can answer.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * THE NUMBER OF OPTIONS IS WHATEVER THE FILE HAS. Any header matching
 * "Option <letter>" is an option column, so a file with six options works
 * unchanged, and a row that leaves Option D blank simply has three.
 *
 * WHAT THIS DOES NOT DECIDE: marks and question type. Those are chosen by the
 * administrator or instructor on the review screen, not carried in the file —
 * so this returns the answer key and lets the caller apply the rest.
 *
 * PARSING NEVER WRITES ANYTHING. Every row comes back either as a candidate or
 * as a problem naming its row number, and the author sees all of it before a
 * single question is created.
 */
final class QuestionImportParser
{
    /**
     * Header names this parser understands, lower-cased for matching.
     */
    private const QUESTION_HEADERS = ['question', 'question text', 'body'];

    private const ANSWER_HEADERS = ['answer', 'correct answer', 'answer key', 'correct'];

    private const EXPLANATION_HEADERS = ['explanation', 'rationale', 'reason'];

    /**
     * A hard ceiling on rows, so a mis-selected 50,000-row export becomes a
     * clear refusal rather than a request that times out halfway through.
     */
    public const MAX_QUESTIONS = 500;

    public function __construct(private readonly XlsxReader $reader) {}

    /**
     * @return array{
     *     questions: list<array{row: int, body: string, explanation: string|null, options: list<array{body: string, is_correct: bool}>, answers: list<string>}>,
     *     problems: list<array{row: int, message: string}>
     * }
     *
     * @throws SpreadsheetException when the file itself cannot be read
     */
    public function parse(string $path): array
    {
        $rows = $this->reader->rows($path);

        if ($rows === []) {
            throw new SpreadsheetException('That spreadsheet is empty.');
        }

        $map = $this->mapHeaders(array_shift($rows));

        if ($rows === []) {
            throw new SpreadsheetException('That spreadsheet has a header row but no questions under it.');
        }

        if (count($rows) > self::MAX_QUESTIONS) {
            throw new SpreadsheetException(sprintf(
                'That file has %d questions and the limit is %d. Split it into smaller files and import them one at a time.',
                count($rows),
                self::MAX_QUESTIONS,
            ));
        }

        $questions = [];
        $problems = [];

        foreach ($rows as $index => $row) {
            // +2: the header was shifted off, and spreadsheet rows are
            // 1-indexed. The number here must match what the author sees in
            // Excel, or "row 7 is broken" sends them to the wrong line.
            $number = $index + 2;

            $parsed = $this->parseRow($row, $map, $number);

            if (isset($parsed['message'])) {
                $problems[] = ['row' => $number, 'message' => $parsed['message']];

                continue;
            }

            $questions[] = $parsed;
        }

        return ['questions' => $questions, 'problems' => $problems];
    }

    /**
     * @param  array<string, string>  $header
     * @return array{question: string, answer: string, explanation: string|null, options: array<string, string>}
     *
     * @throws SpreadsheetException
     */
    private function mapHeaders(array $header): array
    {
        $question = null;
        $answer = null;
        $explanation = null;
        $options = [];

        foreach ($header as $column => $label) {
            $needle = mb_strtolower(trim($label));

            if (in_array($needle, self::QUESTION_HEADERS, true)) {
                $question ??= $column;

                continue;
            }

            if (in_array($needle, self::ANSWER_HEADERS, true)) {
                $answer ??= $column;

                continue;
            }

            if (in_array($needle, self::EXPLANATION_HEADERS, true)) {
                $explanation ??= $column;

                continue;
            }

            // "Option A", "option b", "Option  C" — the letter is the answer
            // key's vocabulary, so it is what the option is filed under.
            if (preg_match('/^option\s*([a-z])$/u', $needle, $matches) === 1) {
                $options[mb_strtoupper($matches[1])] = $column;
            }
        }

        if ($question === null) {
            throw new SpreadsheetException('No "Question" column found. The first row must name the columns — see the template.');
        }

        if ($answer === null) {
            throw new SpreadsheetException('No "Answer" column found. The first row must name the columns — see the template.');
        }

        if ($options === []) {
            throw new SpreadsheetException('No "Option A", "Option B", … columns found. The first row must name the columns — see the template.');
        }

        // A, B, C … so the answer letter and the option order agree.
        ksort($options);

        return ['question' => $question, 'answer' => $answer, 'explanation' => $explanation, 'options' => $options];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{question: string, answer: string, explanation: string|null, options: array<string, string>}  $map
     * @return array{row: int, body: string, explanation: string|null, options: list<array{body: string, is_correct: bool}>, answers: list<string>}|array{message: string}
     */
    private function parseRow(array $row, array $map, int $number): array
    {
        $body = trim($row[$map['question']] ?? '');

        if ($body === '') {
            return ['message' => 'The question text is empty.'];
        }

        // Only option cells that actually hold something. A blank Option D
        // means this question has three options, not four with one empty.
        $present = [];

        foreach ($map['options'] as $letter => $column) {
            $text = trim($row[$column] ?? '');

            if ($text !== '') {
                $present[$letter] = $text;
            }
        }

        if (count($present) < 2) {
            return ['message' => 'Fewer than two options — a choice question needs at least two.'];
        }

        $answers = $this->answerLetters($row[$map['answer']] ?? '');

        if ($answers === []) {
            return ['message' => 'The Answer cell is empty or is not a letter. Use the option letter, for example B (or B,D for more than one).'];
        }

        foreach ($answers as $letter) {
            if (! isset($present[$letter])) {
                return ['message' => sprintf(
                    'The answer is "%s" but there is no option %s on this row.',
                    $letter,
                    $letter,
                )];
            }
        }

        $options = [];

        foreach ($present as $letter => $text) {
            $options[] = ['body' => $text, 'is_correct' => in_array($letter, $answers, true)];
        }

        $explanation = $map['explanation'] === null ? null : trim($row[$map['explanation']] ?? '');

        return [
            'row' => $number,
            'body' => $body,
            'explanation' => $explanation === '' ? null : $explanation,
            'options' => $options,
            'answers' => $answers,
        ];
    }

    /**
     * Read the answer cell as a set of option letters.
     *
     * Accepts "B", "b", "B,D", "B, D", "B D", "B/D". Deliberately does NOT
     * accept "BD" as two letters: that is indistinguishable from a typo, and
     * guessing here would silently mark a second option correct.
     *
     * @return list<string>
     */
    private function answerLetters(string $raw): array
    {
        $parts = preg_split('/[\s,;\/|]+/u', mb_strtoupper(trim($raw)), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false || $parts === []) {
            return [];
        }

        $letters = [];

        foreach ($parts as $part) {
            if (preg_match('/^[A-Z]$/', $part) !== 1) {
                return [];
            }

            // Deduplicated: "B, B" is one correct answer, not two.
            if (! in_array($part, $letters, true)) {
                $letters[] = $part;
            }
        }

        return $letters;
    }
}
