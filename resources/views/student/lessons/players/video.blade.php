{{--
    VIDEO LESSON PLAYER (FR-STU-09, FR-FILE-08, AC-18).

    Resolved through ContentTypeRegistry::playerView() — never referenced by
    name from a controller, which is what lets a new content type be added as
    a handler plus a view with no change to the player itself (ADR-003).

    ═════════════════════════════════════════════════════════════════════════
    THE SRC IS NOT SET HERE, AND THAT IS THE SECURITY PROPERTY.

    This markup never contains a storage path. The <video> element starts
    empty; the browser asks `media.url` for a short-lived URL, the policy runs
    server-side, and only then does a src exist. A view that rendered the path
    directly would be handing out a permanent, unauthorised link in the HTML
    source of every page (architecture.md §16.2).

    So there is no `src` attribute below. That is deliberate, not an omission.
    ═════════════════════════════════════════════════════════════════════════

    Available: $lesson, $media (MediaFile|null), $progress (LessonProgress|null).
--}}
@php
    $resumeAt = $progress?->video_position_seconds ?? 0;
@endphp

<div
    x-data="lessonVideo({
        mediaId: @js($media?->getRouteKey()),
        resumeAt: {{ $resumeAt }},
        urlEndpoint: @js($media ? route('media.url', $media) : null),
    })"
    x-init="load()"
    class="space-y-4"
>
    <div class="overflow-hidden rounded-card border border-neutral-200 bg-neutral-900">
        @if ($media)
            <video
                x-ref="video"
                class="aspect-video w-full"
                controls
                controlslist="nodownload"
                disablepictureinpicture
                preload="metadata"
                x-on:loadedmetadata="seekToResume()"
                x-on:timeupdate.throttle.10000ms="report()"
                x-on:pause="report()"
                x-on:ended="report()"
            >
                {{-- Captions land in Phase 15 with the accessibility pass. --}}
                Your browser cannot play this video.
            </video>

            {{-- The URL expires; a long lecture outlives it. This is the
                 recovery path, and it is visible rather than silent so a
                 student is never left staring at a dead player. --}}
            <div x-show="failed" x-cloak class="p-6 text-center">
                <p class="text-sm text-white/80">This video link expired.</p>
                <button type="button" x-on:click="load()"
                        class="eyebrow mt-3 text-white underline underline-offset-4">
                    Reload video
                </button>
            </div>
        @else
            <div class="p-10 text-center text-sm text-white/70">
                This lesson has no video attached yet.
            </div>
        @endif
    </div>

    {{--
        How close this lesson is to completing itself (FR-PROG-04).

        Shown because the rule is otherwise invisible: a student who stops at
        85% has no way to know why the lesson did not tick, and "it just
        doesn't work" is the reasonable conclusion. The figure is the MAXIMUM
        ever watched, not the current playhead — scrubbing back does not take
        it away.
    --}}
    @php
        $watchedSeconds = $progress?->video_watched_seconds ?? 0;
        $knownDuration = $lesson->duration_seconds ?? $progress?->video_duration_seconds ?? 0;
        $watchedPercent = $knownDuration > 0
            ? min(100, (int) floor(($watchedSeconds / $knownDuration) * 100))
            : null;
    @endphp

    @if ($media && $watchedPercent !== null && $progress?->completed_at === null)
        <div>
            <p class="flex items-baseline justify-between font-mono text-[11px] tracking-[0.04em] text-neutral-500">
                <span>{{ $watchedPercent }}% WATCHED</span>
                <span>COMPLETES AT {{ $videoThreshold }}%</span>
            </p>
            <x-progress-bar :value="$watchedPercent" size="sm" class="mt-1.5" />
        </div>
    @endif

    @if ($lesson->summary)
        <p class="max-w-[68ch] leading-relaxed text-neutral-700">{{ $lesson->summary }}</p>
    @endif

    @if ($lesson->body)
        {{-- Sanitised on save, not on render (NFR-SEC-06) — what is in the
             database is already safe, so this is the one place {!! !!} is
             correct rather than a liability. --}}
        <div class="prose-lms max-w-[68ch] leading-relaxed text-neutral-700">{!! $lesson->body !!}</div>
    @endif
</div>
