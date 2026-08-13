{{--
    Quiz lesson editor (App\Services\Content\Handlers\QuizContentHandler).
    Included inside LessonEditor's form (Phase 8). The lesson's "content" is
    the assessment attached to it, authored on its own screen — this partial
    only links there and carries the lesson-level summary QuizContentHandler
    still validates.
--}}
@php $quizAssessment = $this->assessment(); @endphp

<div class="space-y-4">
    <div>
        <label for="lesson-summary-{{ $lesson->id }}" class="block text-sm font-medium text-neutral-900">
            Description (optional)
        </label>
        <textarea
            wire:model="summary"
            id="lesson-summary-{{ $lesson->id }}"
            rows="3"
            class="mt-1.5 block w-full rounded-control border border-neutral-200 px-3 py-2 text-sm text-neutral-900 hover:border-neutral-300"
            placeholder="What will this quiz cover?&hellip;"
        ></textarea>
        <p class="mt-1.5 text-xs text-neutral-500">Shown to students above the quiz.</p>
    </div>

    <div>
        <span class="mb-1.5 block text-sm font-medium text-neutral-900">Assessment</span>

        @if ($quizAssessment)
            <div class="flex items-center gap-3 rounded-control border border-neutral-200 bg-neutral-50 px-3.5 py-3">
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold text-neutral-900">{{ $quizAssessment->title }}</div>
                    <div class="text-xs text-neutral-500">
                        {{ $quizAssessment->questions_count }} {{ Str::plural('question', $quizAssessment->questions_count) }}
                        &middot; {{ $quizAssessment->total_marks }} {{ Str::plural('mark', (float) $quizAssessment->total_marks) }}
                        &middot; {{ $quizAssessment->is_published ? 'Published' : 'Draft' }}
                    </div>
                </div>
                <x-button :href="route('admin.assessments.builder', $quizAssessment)" variant="secondary" size="sm" wire:navigate>
                    Open assessment
                </x-button>
            </div>
        @else
            <div class="rounded-control border border-dashed border-neutral-300 px-4 py-6 text-center">
                <p class="mb-3 text-sm text-neutral-500">This quiz lesson has no assessment yet.</p>
                <x-button wire:click="createAssessment" size="sm">Create assessment</x-button>
            </div>
        @endif
    </div>
</div>
