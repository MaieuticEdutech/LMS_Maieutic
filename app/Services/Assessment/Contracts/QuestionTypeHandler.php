<?php

declare(strict_types=1);

namespace App\Services\Assessment\Contracts;

use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\Question;

/**
 * One question type's behaviour, in one class (mirrors
 * App\Services\Content\Contracts\LessonContentHandler and ADR-003's
 * extension-point pattern — architecture.md §10.4).
 *
 * Nothing outside this interface should ever branch on QuestionType. Adding
 * "matching" or "fill in the blank" later is one new class plus one registry
 * line, no schema change.
 */
interface QuestionTypeHandler
{
    public function type(): QuestionType;

    /**
     * Human label for the "add question" type picker.
     */
    public function label(): string;

    /**
     * Blade view rendering this type's editor in the question authoring UI.
     */
    public function editorView(): string;

    /**
     * Does this type store its answer key in `question_options` (choice
     * types) rather than `questions.meta` (short answer)?
     */
    public function requiresOptions(): bool;

    /**
     * Validation rules for this type's own fields, merged on top of the
     * shared question rules (body, marks, negative_marks) — e.g. short
     * answer's `meta.accepted_answers`.
     *
     * @return array<string, mixed>
     */
    public function validationRules(): array;

    /**
     * Validate the answer key beyond basic Laravel rules — FR-ASMT-07:
     * single-choice/true-false need exactly one correct option,
     * multiple-choice needs at least one. Called after validationRules()
     * passes. Returns every problem found, not just the first, so an author
     * fixes everything in one pass.
     *
     * @param  list<array{body: string, is_correct: bool}>  $options
     * @return list<string>
     */
    public function validateAnswerKey(array $options): array;

    /**
     * Server-side grading (FR-ASMT-12, NFR-SEC-21) — never trusts
     * client-reported correctness. Marks/negative-marks arithmetic is
     * GradingService's job, kept out of every handler so it is written once;
     * this method answers only "was this specific answer correct".
     */
    public function grade(Question $question, AttemptAnswer $answer): bool;

    /**
     * The question as a student may see it BEFORE submission — no
     * `is_correct`, no accepted-answer list (AC-23). QuestionPresenter calls
     * this for every question in an attempt.
     *
     * @return array<string, mixed>
     */
    public function presentForStudent(Question $question): array;
}
