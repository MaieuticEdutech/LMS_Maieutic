<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Enums\QuestionType;
use App\Services\Assessment\Contracts\QuestionTypeHandler;
use InvalidArgumentException;

/**
 * Mirrors App\Services\Content\ContentTypeRegistry exactly — the same
 * extension-point shape, one registry away from the content one (§10.4).
 */
final class QuestionTypeRegistry
{
    /** @var array<string, QuestionTypeHandler> */
    private array $handlers = [];

    public function register(QuestionTypeHandler $handler): void
    {
        $this->handlers[$handler->type()->value] = $handler;
    }

    public function for(QuestionType $type): QuestionTypeHandler
    {
        return $this->handlers[$type->value]
            ?? throw new InvalidArgumentException("No handler registered for question type [{$type->value}].");
    }

    public function has(QuestionType $type): bool
    {
        return array_key_exists($type->value, $this->handlers);
    }

    /**
     * @return array<string, QuestionTypeHandler>
     */
    public function all(): array
    {
        return $this->handlers;
    }
}
