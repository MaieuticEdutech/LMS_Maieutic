<div>
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.assessments.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold text-neutral-700 hover:text-teal-700">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
            Assessments
        </a>
        <span class="text-neutral-300" aria-hidden="true">/</span>
        <h1 class="flex-1 text-2xl">{{ $assessment->title }}</h1>

        <x-badge :variant="$assessment->is_published ? 'success' : 'neutral'">
            {{ $assessment->is_published ? 'Published' : 'Draft' }}
        </x-badge>

        <x-button type="submit" form="assessment-meta-form" variant="secondary" size="sm">Save draft</x-button>

        @if (! $assessment->is_published)
            <x-button wire:click="publish" size="sm">Publish</x-button>
        @else
            <x-button wire:click="unpublish" variant="secondary" size="sm">Unpublish</x-button>
        @endif
        <x-button x-data x-on:click="$dispatch('open-modal', 'delete-assessment')" variant="danger" size="sm">Delete</x-button>
    </div>

    @if ($this->publishBlockers !== [])
        <x-alert variant="warning" title="Not ready to publish" class="mb-5">
            <ul class="list-inside list-disc space-y-0.5">
                @foreach ($this->publishBlockers as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[var(--spacing-builder-aside)_1fr] lg:items-start">
        <x-card title="Assessment settings">
            <form id="assessment-meta-form" wire:submit="save" class="space-y-4">
                <x-input wire:model="title" label="Title" name="title" required />

                <div>
                    <label for="instructions" class="block text-sm font-medium text-neutral-900">Instructions (optional)</label>
                    <textarea wire:model="instructions" id="instructions" rows="3" class="mt-1.5 block w-full rounded-control border border-neutral-200 px-3 py-2 text-sm text-neutral-900 hover:border-neutral-300"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <x-input wire:model="passing_percentage" type="number" min="0" max="100" label="Passing %" name="passing_percentage" required />
                    <x-input wire:model="time_limit_minutes" type="number" min="1" label="Time limit (min, optional)" name="time_limit_minutes" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <x-input wire:model="max_attempts" type="number" min="1" label="Max attempts (optional)" name="max_attempts" hint="Blank = unlimited." />
                    <x-select wire:model="scoring_policy" label="Official score" name="scoring_policy">
                        @foreach (\App\Enums\ScoringPolicy::cases() as $policy)
                            <option value="{{ $policy->value }}">{{ ucfirst($policy->value) }} attempt</option>
                        @endforeach
                    </x-select>
                </div>

                <x-select wire:model="answer_reveal" label="Answer reveal" name="answer_reveal">
                    @foreach (\App\Enums\AnswerRevealPolicy::cases() as $policy)
                        <option value="{{ $policy->value }}">{{ ucfirst(str_replace('_', ' ', $policy->value)) }}</option>
                    @endforeach
                </x-select>

                <x-checkbox wire:model="shuffle_questions" label="Shuffle question order per attempt" name="shuffle_questions" />
                <x-checkbox wire:model="shuffle_options" label="Shuffle option order per attempt" name="shuffle_options" />
                <x-checkbox wire:model="negative_marking_enabled" label="Enable negative marking" name="negative_marking_enabled" />
            </form>
        </x-card>

        <div class="min-w-0">
            <div class="mb-3 flex items-baseline justify-between">
                <h2>Questions</h2>
                <span class="text-xs text-neutral-500">
                    {{ $assessment->questions_count }} {{ Str::plural('question', $assessment->questions_count) }}
                    &middot; {{ $assessment->total_marks }} {{ Str::plural('mark', (float) $assessment->total_marks) }}
                </span>
            </div>

            <livewire:admin.assessments.question-list :assessment="$assessment" wire:key="questions-{{ $assessment->id }}" />
        </div>
    </div>

    <div x-data="{ confirmation: '' }">
        <x-modal name="delete-assessment" title="Delete assessment">
            <p class="text-sm text-neutral-600">
                This permanently deletes <span class="font-medium">{{ $assessment->title }}</span> and its questions.
                Type the assessment title to confirm.
            </p>

            <x-input x-model="confirmation" class="mt-3" placeholder="{{ $assessment->title }}" />

            <x-slot:footer>
                <x-button x-on:click="$dispatch('close-modal', 'delete-assessment')" variant="secondary" size="sm">Cancel</x-button>
                <button
                    type="button"
                    wire:click="delete"
                    x-bind:disabled="confirmation !== '{{ $assessment->title }}'"
                    class="inline-flex items-center justify-center rounded-control bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Delete assessment
                </button>
            </x-slot:footer>
        </x-modal>
    </div>
</div>
