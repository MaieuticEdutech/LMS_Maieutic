{{--
    Quiz lesson editor (App\Services\Content\Handlers\QuizContentHandler).

    ═════════════════════════════════════════════════════════════════════════
    THIS EXISTS SO THE EDITOR CANNOT CRASH, NOT SO QUIZZES CAN BE AUTHORED.

    ContentTypeRegistry declares an editor view for every registered type, and
    Quiz is registered — it is a case in LessonType and a lesson row can
    already hold it. Without this file, opening such a lesson in the builder
    throws "View not found".

    Quiz is excluded from selectableTypes(), so an author cannot choose it from
    the type list today. That makes this reachable only by an existing quiz row
    — a seeded one, or one created before the exclusion. Rare, and a blank
    screen with a stack trace is a poor way to meet it.

    NOT BUILDING AHEAD (Rule 5). No question builder, no options, no answer
    key: that is Phase 8's Assessment Engine. QuizContentHandler still returns
    a publish blocker, so a course containing one cannot go live regardless of
    what is typed here.
    ═════════════════════════════════════════════════════════════════════════

    The sibling player view (student/lessons/players/quiz.blade.php) carries
    the matching message for the student side.
--}}
<div class="space-y-4">
    <x-alert variant="info" title="Assessment authoring is not available yet">
        Questions, options and answer keys arrive with the Assessment Engine. This
        lesson can be saved and reordered, but it cannot be published until then —
        the publish checklist will say so.
    </x-alert>

    <div>
        <label for="lesson-summary-{{ $lesson->id }}" class="block text-sm font-medium text-neutral-900">
            Description
        </label>

        {{-- Summary is editable because it is a plain lesson column that Phase 8
             does not own. Letting an author describe the quiz now costs nothing
             and is not the assessment itself. --}}
        <textarea
            wire:model="summary"
            id="lesson-summary-{{ $lesson->id }}"
            rows="4"
            class="mt-1.5 block w-full rounded-control border border-neutral-200 px-3 py-2 text-sm text-neutral-900 hover:border-neutral-300"
            placeholder="What will this quiz cover?&hellip;"
        ></textarea>

        <p class="mt-1.5 text-xs text-neutral-500">
            Shown to students above the quiz once the assessment runner is available.
        </p>
    </div>
</div>
