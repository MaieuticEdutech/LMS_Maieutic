<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Content\ContentTypeRegistry;
use App\Services\Content\Handlers\DocumentContentHandler;
use App\Services\Content\Handlers\PresentationContentHandler;
use App\Services\Content\Handlers\QuizContentHandler;
use App\Services\Content\Handlers\ResourceContentHandler;
use App\Services\Content\Handlers\TextContentHandler;
use App\Services\Content\Handlers\VideoContentHandler;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the content type handlers (ADR-003, FR-CNT-07).
 *
 * THIS IS THE EXTENSION POINT. Adding a content type means adding one line
 * here plus one handler class — no schema change, no edit to any other class,
 * no `match` statement to hunt down (principle P-7).
 *
 * The registry is a singleton because it is pure configuration: the handlers
 * are stateless, and rebuilding the list on every resolve would be waste.
 */
class ContentServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(ContentTypeRegistry::class, static function (): ContentTypeRegistry {
            $registry = new ContentTypeRegistry;

            // ── Types an administrator can author today ──────────────────
            $registry->register(new VideoContentHandler);
            $registry->register(new DocumentContentHandler);
            $registry->register(new PresentationContentHandler);
            $registry->register(new ResourceContentHandler);
            $registry->register(new TextContentHandler);

            /*
             * Registered but NOT selectable until Phase 8 builds the
             * assessment engine. It is registered rather than omitted because
             * the registry throws on an unregistered type, and `quiz` is a
             * legal value the database will accept — a missing handler would
             * turn any encounter with a quiz lesson into a hard failure.
             * See QuizContentHandler.
             */
            $registry->register(new QuizContentHandler);

            return $registry;
        });
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [ContentTypeRegistry::class];
    }
}
