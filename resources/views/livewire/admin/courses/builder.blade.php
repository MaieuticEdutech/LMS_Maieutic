<div>
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.courses.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold text-neutral-700 hover:text-teal-700">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
            Courses
        </a>
        <span class="text-neutral-300" aria-hidden="true">/</span>
        <h1 class="flex-1 text-2xl">{{ $course?->title ?: 'New course' }}</h1>

        @if ($course)
            <x-badge :variant="$course->status->badgeVariant()">{{ $course->status->label() }}</x-badge>
        @endif

        <x-button type="submit" form="course-meta-form" variant="secondary" size="sm">Save draft</x-button>

        @if ($course)
            @php $status = $course->status; @endphp
            @if ($status !== \App\Enums\CourseStatus::Published)
                <x-button wire:click="publish" size="sm" wire:loading.attr="disabled" wire:target="publish">Publish</x-button>
            @endif
            @if ($status === \App\Enums\CourseStatus::Published)
                <x-button wire:click="unpublish" variant="secondary" size="sm">Unpublish</x-button>
            @endif
            @if ($status !== \App\Enums\CourseStatus::Archived)
                <x-button wire:click="archive" variant="secondary" size="sm">Archive</x-button>
            @endif
            <x-button x-on:click="$dispatch('open-modal', 'delete-course')" variant="danger" size="sm">Delete</x-button>
        @endif
    </div>

    @if ($course && $this->publishBlockers !== [])
        <x-alert variant="warning" title="Not ready to publish" class="mb-5">
            <ul class="list-inside list-disc space-y-0.5">
                @foreach ($this->publishBlockers as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[var(--spacing-builder-aside)_1fr] lg:items-start">
        <x-card title="Course information">
            <form id="course-meta-form" wire:submit="save" class="space-y-4">
                <x-input wire:model="title" label="Course title" name="title" required />

                <div>
                    <label for="description" class="block text-sm font-medium text-neutral-900">Description</label>
                    <textarea wire:model="description" id="description" rows="4" class="mt-1.5 block w-full rounded-control border border-neutral-200 px-3 py-2 text-sm text-neutral-900 hover:border-neutral-300"></textarea>
                </div>

                <x-input wire:model="subtitle" label="Subtitle (optional)" name="subtitle" hint="Shown on catalogue cards." />

                <div class="grid grid-cols-2 gap-3">
                    <x-select wire:model="category_id" label="Category" name="category_id" placeholder="Uncategorised">
                        @foreach ($this->categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </x-select>

                    <x-input wire:model="priceRupees" type="number" step="0.01" min="0" label="Price (INR)" name="priceRupees" required />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <x-select wire:model="level" label="Level" name="level" required>
                        @foreach (\App\Enums\CourseLevel::cases() as $levelOption)
                            <option value="{{ $levelOption->value }}">{{ $levelOption->label() }}</option>
                        @endforeach
                    </x-select>

                    <x-input wire:model="language" label="Language" name="language" required />
                </div>

                <x-checkbox wire:model="requires_final_test" label="Requires a final test to complete" name="requires_final_test" />

                <div>
                    <span class="block text-sm font-medium text-neutral-900">What you'll learn</span>
                    <div class="mt-1.5 space-y-2">
                        @foreach ($outcomes as $i => $outcome)
                            <div class="flex gap-2">
                                <input wire:model="outcomes.{{ $i }}" type="text" class="block h-10 w-full rounded-control border border-neutral-200 px-3 text-sm text-neutral-900 hover:border-neutral-300" placeholder="e.g. Build a REST API from scratch">
                                <button type="button" wire:click="removeOutcome({{ $i }})" class="shrink-0 rounded-control border border-neutral-200 px-2.5 text-neutral-500 hover:border-red-200 hover:text-red-600" aria-label="Remove">&times;</button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addOutcome" class="mt-2 text-sm font-semibold text-teal-700 hover:text-teal-800">+ Add outcome</button>
                </div>

                <div>
                    <span class="block text-sm font-medium text-neutral-900">Requirements</span>
                    <div class="mt-1.5 space-y-2">
                        @foreach ($requirements as $i => $requirement)
                            <div class="flex gap-2">
                                <input wire:model="requirements.{{ $i }}" type="text" class="block h-10 w-full rounded-control border border-neutral-200 px-3 text-sm text-neutral-900 hover:border-neutral-300" placeholder="e.g. Basic JavaScript">
                                <button type="button" wire:click="removeRequirement({{ $i }})" class="shrink-0 rounded-control border border-neutral-200 px-2.5 text-neutral-500 hover:border-red-200 hover:text-red-600" aria-label="Remove">&times;</button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addRequirement" class="mt-2 text-sm font-semibold text-teal-700 hover:text-teal-800">+ Add requirement</button>
                </div>

                <div>
                    <span class="block text-sm font-medium text-neutral-900">Thumbnail</span>
                    <div class="mt-1.5">
                        @if ($course)
                            <livewire:admin.courses.media-uploader
                                :attachable="$course"
                                purpose="thumbnail"
                                :multiple="false"
                                :downloadable="false"
                                wire:key="thumbnail-{{ $course->id }}"
                            />
                        @else
                            <p class="rounded-control border border-dashed border-neutral-300 px-4 py-6 text-center text-xs text-neutral-500">
                                Save the course once to add a thumbnail.
                            </p>
                        @endif
                    </div>
                </div>
            </form>
        </x-card>

        <div class="min-w-0">
            <div class="mb-3 flex items-baseline justify-between">
                <h2>Course structure</h2>
                @if ($course)
                    <span class="text-xs text-neutral-500">
                        {{ $course->modules_count }} {{ Str::plural('module', $course->modules_count) }}
                        &middot; {{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}
                        @if ($course->total_duration_seconds > 0)
                            &middot; {{ intdiv($course->total_duration_seconds, 3600) }}h {{ intdiv($course->total_duration_seconds % 3600, 60) }}m
                        @endif
                    </span>
                @endif
            </div>

            @if ($course)
                <livewire:admin.courses.module-list :course="$course" wire:key="modules-{{ $course->id }}" />
            @else
                <x-empty-state title="Save the course to start building" description="Fill in a title and price on the left and save a draft — module and lesson tools appear once the course exists." />
            @endif
        </div>
    </div>

    @if ($course)
        <div x-data="{ confirmation: '' }">
            <x-modal name="delete-course" title="Delete course">
                <p class="text-sm text-neutral-600">
                    This soft-deletes <span class="font-medium">{{ $course->title }}</span> and queues its files for
                    cleanup. Type the course title to confirm.
                </p>

                <x-input x-model="confirmation" class="mt-3" placeholder="{{ $course->title }}" />

                <x-slot:footer>
                    <x-button x-on:click="$dispatch('close-modal', 'delete-course')" variant="secondary" size="sm">Cancel</x-button>
                    <button
                        type="button"
                        wire:click="delete"
                        x-bind:disabled="confirmation !== '{{ $course->title }}'"
                        class="inline-flex items-center justify-center rounded-control bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Delete course
                    </button>
                </x-slot:footer>
            </x-modal>
        </div>
    @endif
</div>
