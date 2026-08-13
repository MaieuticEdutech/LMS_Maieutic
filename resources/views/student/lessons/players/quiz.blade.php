{{--
    QUIZ LESSON PLAYER (Phase 8). Available: $lesson, $media (null), $progress.

    The lesson's "content" is the Assessment attached to it — resolved the
    same way QuizContentHandler does, so this partial never touches the
    assessments/questions schema directly. requires_final_test-driven course
    completion (FR-ASMT-19) is Phase 9's concern, not this view's.
--}}
@php
    $quizAssessment = \App\Models\Assessment::query()
        ->where('assessable_type', \App\Models\Lesson::class)
        ->where('assessable_id', $lesson->id)
        ->first();
@endphp

@if ($quizAssessment === null || ! $quizAssessment->is_published)
    <x-empty-state
        title="This quiz isn't ready yet"
        description="Check back soon — your progress in the rest of the course is unaffected."
    />
@else
    <div class="rounded-card border border-neutral-200 bg-white p-6">
        <p class="eyebrow text-teal-600">{{ ucfirst($quizAssessment->type->value) }}</p>
        <h2 class="mt-1.5 text-xl">{{ $quizAssessment->title }}</h2>

        @if ($quizAssessment->instructions)
            <p class="mt-2 text-sm text-neutral-600">{{ $quizAssessment->instructions }}</p>
        @endif

        <div class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-xs text-neutral-500">
            <span>{{ $quizAssessment->questions_count }} {{ Str::plural('question', $quizAssessment->questions_count) }}</span>
            <span>Pass mark {{ $quizAssessment->passing_percentage }}%</span>
            @if ($quizAssessment->time_limit_minutes)
                <span>{{ $quizAssessment->time_limit_minutes }} minute limit</span>
            @endif
            @if ($quizAssessment->max_attempts)
                <span>{{ $quizAssessment->max_attempts }} {{ Str::plural('attempt', $quizAssessment->max_attempts) }} allowed</span>
            @endif
        </div>

        <div class="mt-5 flex gap-3">
            <x-button :href="route('student.assessments.attempt', $quizAssessment)" wire:navigate>Start / continue</x-button>
            <x-button :href="route('student.assessments.history', $quizAssessment)" variant="secondary" wire:navigate>Attempt history</x-button>
        </div>
    </div>
@endif
