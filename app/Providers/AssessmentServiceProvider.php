<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Assessment\Handlers\MultipleChoiceHandler;
use App\Services\Assessment\Handlers\ShortAnswerHandler;
use App\Services\Assessment\Handlers\SingleChoiceHandler;
use App\Services\Assessment\Handlers\TrueFalseHandler;
use App\Services\Assessment\QuestionTypeRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the question type handlers (architecture.md §10.4) — mirrors
 * App\Providers\ContentServiceProvider, one registry over from the content
 * one. Also registers the `attempt-submit` rate limiter (phases.md Phase 8
 * backend work).
 *
 * NOT deferred, unlike ContentServiceProvider: boot() has a global side
 * effect (registering the rate limiter) that must run on every request,
 * not only when QuestionTypeRegistry happens to be resolved first.
 */
class AssessmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QuestionTypeRegistry::class, static function (): QuestionTypeRegistry {
            $registry = new QuestionTypeRegistry;

            $registry->register(new SingleChoiceHandler);
            $registry->register(new MultipleChoiceHandler);
            $registry->register(new TrueFalseHandler);
            $registry->register(new ShortAnswerHandler);

            return $registry;
        });
    }

    public function boot(): void
    {
        // Keyed per user, not per IP: a shared IP (college lab, office) is
        // normal, and a submit is a rare, deliberate action — unlike the
        // media limiter's high-frequency reads, a tight per-user ceiling is
        // appropriate here without punishing everyone on the same network.
        //
        // Registered as a named limiter for any future HTTP endpoint, but
        // Student\AttemptRunner::submit() cannot reach it via `throttle:`
        // route middleware — every Livewire component call funnels through
        // Livewire's own shared update endpoint, not through
        // routes/student.php's named routes. It checks this same
        // `attempt-submit:user:{id}` key directly against RateLimiter
        // instead, at the same 10-per-minute ceiling defined here.
        RateLimiter::for('attempt-submit', static function (Request $request): Limit {
            $user = $request->user();

            return $user !== null
                ? Limit::perMinute(10)->by('attempt-submit:user:'.$user->getAuthIdentifier())
                : Limit::perMinute(5)->by('attempt-submit:ip:'.(string) $request->ip());
        });
    }
}
