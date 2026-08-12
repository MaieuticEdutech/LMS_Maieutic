<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\LessonType;
use App\Services\Content\Contracts\LessonContentHandler;
use InvalidArgumentException;

/**
 * Resolves a LessonType to the class that knows how to handle it
 * (ADR-003, FR-CNT-07, architecture.md §9.2).
 *
 * EXTEND BY REGISTRATION, NEVER BY MODIFICATION (principle P-7).
 *
 * Nothing outside a handler should branch on LessonType. When you find
 * yourself writing `match ($lesson->type)` somewhere else, that logic belongs
 * on the handler instead — otherwise adding a content type means hunting
 * every such branch, and missing one produces a type that half-works.
 *
 * Handlers are registered in ContentServiceProvider. A test may register its
 * own to prove the extension point genuinely works without a schema change —
 * which is exactly what the Phase 5 test suite does.
 */
final class ContentTypeRegistry
{
    /**
     * @var array<string, LessonContentHandler>
     */
    private array $handlers = [];

    public function register(LessonContentHandler $handler): void
    {
        $this->handlers[$handler->type()->value] = $handler;
    }

    /**
     * The handler for a type.
     *
     * Throws rather than returning null: every registered LessonType must
     * have a handler, and a missing one is a wiring bug that should surface
     * immediately rather than degrade into a blank page.
     */
    public function for(LessonType $type): LessonContentHandler
    {
        return $this->handlers[$type->value] ?? throw new InvalidArgumentException(
            "No content handler registered for lesson type [{$type->value}]. "
            .'Register one in ContentServiceProvider rather than branching on the type.',
        );
    }

    public function has(LessonType $type): bool
    {
        return isset($this->handlers[$type->value]);
    }

    /**
     * Every registered handler, for the Course Builder's type picker.
     *
     * @return array<string, LessonContentHandler>
     */
    public function all(): array
    {
        return $this->handlers;
    }

    /**
     * Types an administrator may choose when creating a lesson.
     *
     * `quiz` is excluded until Phase 8 builds the assessment engine: offering
     * a type that cannot yet be authored would let an admin create a lesson
     * that can never be completed.
     *
     * @return list<LessonType>
     */
    public function selectableTypes(): array
    {
        return array_values(array_filter(
            array_map(
                static fn (LessonContentHandler $h): LessonType => $h->type(),
                $this->handlers,
            ),
            static fn (LessonType $type): bool => $type !== LessonType::Quiz,
        ));
    }
}
