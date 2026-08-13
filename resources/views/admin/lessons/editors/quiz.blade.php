{{--
    Quiz lesson editor (App\Services\Content\Handlers\QuizContentHandler).
    Included inside LessonEditor's form (Phase 8). No file upload and no
    lesson-level field of its own — the lesson's "content" is the assessment
    attached to it, authored on its own screen.
--}}
@php $quizAssessment = $this->assessment(); @endphp

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
