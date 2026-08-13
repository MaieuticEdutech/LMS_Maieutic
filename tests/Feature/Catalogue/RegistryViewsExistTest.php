<?php

declare(strict_types=1);

use App\Enums\LessonType;
use App\Services\Content\ContentTypeRegistry;
use Illuminate\Support\Facades\View;

/*
|--------------------------------------------------------------------------
| ContentTypeRegistry · every declared view actually exists (ADR-003)
|--------------------------------------------------------------------------
|
| THE GAP THIS CLOSES WAS SILENT FOR THREE PHASES.
|
| Each handler names an editor view and a player view. Nothing verified they
| existed, so from Phase 5 until Phase 7 all twelve were missing and every
| call to playerView() or editorView() would have thrown "View not found".
| No test failed, because no code called them yet.
|
| That is the worst shape a defect can take: correct-looking, committed,
| and waiting. This asserts the registry's contract directly rather than
| waiting for a screen to be built that happens to exercise it.
|
| A NEW CONTENT TYPE FAILS HERE FIRST. Register a handler without its two
| views and this test names both missing files — which is the moment to find
| out, rather than when an author opens the lesson.
|
*/

it('has an editor view for every registered content type', function (LessonType $type): void {
    $view = app(ContentTypeRegistry::class)->for($type)->editorView();

    expect(View::exists($view))->toBeTrue(
        "[{$type->value}] declares editor view [{$view}], which does not exist.",
    );
})->with(LessonType::cases());

it('has a player view for every registered content type', function (LessonType $type): void {
    $view = app(ContentTypeRegistry::class)->for($type)->playerView();

    expect(View::exists($view))->toBeTrue(
        "[{$type->value}] declares player view [{$view}], which does not exist.",
    );
})->with(LessonType::cases());

it('registers a handler for every case of LessonType', function (LessonType $type): void {
    // A type in the enum with no handler is the other half of the same
    // problem: the database can hold it and nothing can render it.
    expect(app(ContentTypeRegistry::class)->has($type))->toBeTrue(
        "LessonType::{$type->name} has no registered handler.",
    );
})->with(LessonType::cases());
