{{--
    QUIZ LESSON PLAYER — placeholder until Phase 8.

    ═════════════════════════════════════════════════════════════════════════
    THIS VIEW EXISTS SO THE PLAYER CANNOT CRASH, NOT SO QUIZZES WORK.

    ContentTypeRegistry declares a view for every registered type, and Quiz is
    registered — it appears in `LessonType`, and a lesson row can already carry
    it. Without this file the player would throw "View not found" the moment
    anyone reached such a lesson.

    It is NOT building ahead (Rule 5). No attempt runner, no question
    rendering, no scoring — that is Phase 8's Assessment Engine, and
    QuizContentHandler still returns a publish blocker so a course containing
    one cannot go live. This is the honest state made visible instead of a
    stack trace.
    ═════════════════════════════════════════════════════════════════════════

    Available: $lesson, $media (null), $progress.
--}}
<x-empty-state
    title="Assessments are not available yet"
    description="This lesson is a quiz. The assessment runner arrives in a later release — your progress in the rest of the course is unaffected."
/>

@if ($lesson->summary)
    <p class="mt-5 max-w-[68ch] leading-relaxed text-neutral-700">{{ $lesson->summary }}</p>
@endif
