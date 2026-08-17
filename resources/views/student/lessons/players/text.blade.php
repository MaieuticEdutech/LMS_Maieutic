{{--
    TEXT LESSON PLAYER (FR-STU-09).

    The simplest of the six, and the one that proves the registry pattern:
    a content type with no media at all needs no special case anywhere in the
    player — only this view.

    $lesson->body is sanitised ON SAVE by HtmlSanitizer (NFR-SEC-06), so what
    is in the database is already safe to render. That is why {!! !!} is
    correct here rather than a liability — sanitising again on every render
    would be slower and would not make it any safer.

    Available: $lesson, $media (null for this type), $progress.
--}}
<article class="space-y-5">
    {{-- The summary is drawn once by the player, below the lesson title
         (design handoff §4) — see video.blade.php. --}}
    @if ($lesson->body)
        {{-- Capped at 68ch: a reading measure running the full width of a
             desktop screen is the fastest way to make prose unreadable. --}}
        <div class="prose-lms max-w-[68ch] leading-relaxed text-neutral-800">{!! $lesson->body !!}</div>
    @else
        <p class="text-sm text-neutral-500">This lesson has no content yet.</p>
    @endif
</article>
