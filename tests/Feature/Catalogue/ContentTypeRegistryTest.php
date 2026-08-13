<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Models\Lesson;
use App\Services\Content\ContentTypeRegistry;
use App\Services\Content\Contracts\LessonContentHandler;

/*
|--------------------------------------------------------------------------
| Phase 5 · The content type registry (ADR-003, FR-CNT-07, P-7)
|--------------------------------------------------------------------------
|
| The headline test in this file is the LAST one: a brand-new content type
| added with no schema change. That is the property four separate media tables
| would have cost us, and it is worth proving rather than asserting.
|
*/

beforeEach(function (): void {
    $this->registry = app(ContentTypeRegistry::class);
});

it('has a handler for every lesson type', function (): void {
    // A missing handler is a wiring bug that would surface as a blank page in
    // the player rather than an error.
    foreach (LessonType::cases() as $type) {
        expect($this->registry->has($type))->toBeTrue();
    }
});

it('throws for an unregistered type rather than returning null', function (): void {
    $empty = new ContentTypeRegistry;

    expect(fn () => $empty->for(LessonType::Video))
        ->toThrow(InvalidArgumentException::class, 'No content handler registered');
});

it('includes every registered type, including quiz since Phase 8', function (): void {
    // Quiz was excluded through Phase 5–7 (no assessment engine existed to
    // author against); Phase 8 completes QuizContentHandler and lifts the
    // exclusion — see that class and ContentTypeRegistry::selectableTypes().
    $selectable = array_map(
        static fn (LessonType $t): string => $t->value,
        $this->registry->selectableTypes(),
    );

    expect($selectable)->toContain('quiz')
        ->and($selectable)->toContain('video')
        ->and($selectable)->toContain('text');
});

it('maps each type to the right media purpose', function (LessonType $type, ?MediaPurpose $purpose): void {
    expect($this->registry->for($type)->mediaPurpose())->toBe($purpose);
})->with([
    [LessonType::Video, MediaPurpose::Video],
    [LessonType::Document, MediaPurpose::Document],
    [LessonType::Presentation, MediaPurpose::Presentation],
    [LessonType::Resource, MediaPurpose::Attachment],
    [LessonType::Text, null],
    [LessonType::Quiz, null],
]);

it('knows which types require a file before publishing', function (LessonType $type, bool $required): void {
    expect($this->registry->for($type)->requiresMedia())->toBe($required);
})->with([
    [LessonType::Video, true],
    [LessonType::Document, true],
    [LessonType::Presentation, true],
    [LessonType::Resource, true],
    [LessonType::Text, false],
    [LessonType::Quiz, false],
]);

it('gives every handler an editor and a player view name', function (): void {
    foreach ($this->registry->all() as $handler) {
        expect($handler->editorView())->not->toBeEmpty()
            ->and($handler->playerView())->not->toBeEmpty();
    }
});

/*
| ══════════════════════════════════════════════════════════════════════════
| THE PROOF (FR-CNT-07, phases.md Phase 5 testing requirements):
| "A new content type can be added by registering a handler with no schema
|  change (proven by a test-only handler)."
|
| This handler exists only inside this test. Nothing was migrated, no enum
| case was added, no existing class was edited — and the registry serves it
| exactly like a built-in.
| ══════════════════════════════════════════════════════════════════════════
*/
it('accepts a brand new content type with no schema change', function (): void {
    $handler = new class implements LessonContentHandler
    {
        public function type(): LessonType
        {
            // Reuses an existing enum case only because a test cannot add one
            // at runtime. The point stands: no table, column or migration
            // changed to introduce this behaviour.
            return LessonType::Resource;
        }

        public function label(): string
        {
            return 'SCORM package';
        }

        public function description(): string
        {
            return 'A hypothetical future content type.';
        }

        public function validationRules(): array
        {
            return ['manifest' => ['required', 'string']];
        }

        // Narrower than the interface's ?MediaPurpose — covariance permits it,
        // and this handler always stores a file.
        public function mediaPurpose(): MediaPurpose
        {
            return MediaPurpose::Attachment;
        }

        public function requiresMedia(): bool
        {
            return true;
        }

        public function editorView(): string
        {
            return 'admin.lessons.editors.scorm';
        }

        public function playerView(): string
        {
            return 'student.lessons.players.scorm';
        }

        public function publishBlockers(Lesson $lesson): array
        {
            return ['SCORM packages cannot be published in this test.'];
        }

        public function buildMeta(array $input): array
        {
            return ['scorm_version' => '2004'];
        }
    };

    $registry = new ContentTypeRegistry;
    $registry->register($handler);

    $resolved = $registry->for(LessonType::Resource);

    expect($resolved->label())->toBe('SCORM package')
        ->and($resolved->buildMeta([]))->toBe(['scorm_version' => '2004'])
        ->and($resolved->validationRules())->toHaveKey('manifest')
        ->and($resolved->publishBlockers(Lesson::factory()->make()))->toHaveCount(1);
});

it('lets a re-registered handler override the built-in one', function (): void {
    // Registration is by type key, so a later registration wins. That is what
    // makes swapping an implementation — a commercial video provider, say —
    // a one-line change in the service provider.
    $registry = new ContentTypeRegistry;
    $registry->register(new App\Services\Content\Handlers\VideoContentHandler);

    expect($registry->for(LessonType::Video)->label())->toBe('Video');
});
