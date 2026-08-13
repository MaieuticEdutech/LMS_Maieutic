{{--
    Grant course access directly (Phase 6, FR-ENR-06).

    One clear message, one primary action (UI-GUIDE.md §13). The form is
    deliberately narrow — a full-width row of inputs on a 1920px screen is
    harder to complete than a single column, not easier.
--}}
<div class="max-w-2xl">
    <div class="mb-6">
        <p class="font-mono text-[11px] uppercase tracking-[0.14em] text-neutral-500">Enrolments</p>
        <h1 class="mt-1 font-serif text-3xl font-semibold tracking-tight text-neutral-900">Grant access</h1>
        <p class="mt-1 text-sm text-neutral-500">
            Give a student access to a course without a purchase. The grant is recorded against your name.
        </p>
    </div>

    @if ($students->isEmpty() || $courses->isEmpty())
        {{-- Empty state before the form, not after a failed submit: a form
             that cannot be completed should say so before it is filled in. --}}
        <x-empty-state
            title="{{ $students->isEmpty() ? 'No students to enrol' : 'No courses to grant' }}"
            description="{{ $students->isEmpty()
                ? 'Access is granted to a student account. Create one first, then come back.'
                : 'There are no courses yet. Create a course before granting access to it.' }}"
        >
            <x-slot:action>
                <x-button variant="primary" :href="$students->isEmpty() ? route('admin.students.create') : route('admin.courses.index')">
                    {{ $students->isEmpty() ? 'Add a student' : 'Go to courses' }}
                </x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <form wire:submit="save" class="space-y-5 rounded-card border border-neutral-200 bg-white p-6">
            <x-select wire:model="studentId" name="studentId" label="Student" placeholder="Choose a student" required>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}">{{ $student->name }} — {{ $student->email }}</option>
                @endforeach
            </x-select>

            <x-select wire:model="courseId" name="courseId" label="Course" placeholder="Choose a course" required>
                @foreach ($courses as $course)
                    {{-- Drafts are grantable on purpose — see the component
                         docblock — but they are marked so it is a deliberate
                         choice rather than an accident. --}}
                    <option value="{{ $course->id }}">
                        {{ $course->title }}@if ($course->status === $draftStatus) (draft)@endif
                    </option>
                @endforeach
            </x-select>

            <x-input
                wire:model="expiresAt"
                name="expiresAt"
                type="date"
                label="Access ends"
                hint="Optional. Leave empty for access that does not expire."
            />

            <x-textarea
                wire:model="reason"
                name="reason"
                label="Reason"
                hint="Why this access is being granted — for example a scholarship, a staff account, or a payment taken outside the system."
                :rows="3"
                required
            />

            <div class="flex items-center justify-end gap-2 border-t border-neutral-200 pt-5">
                <x-button variant="secondary" :href="route('admin.enrollments.index')">Cancel</x-button>
                <x-button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Grant access</span>
                    <span wire:loading wire:target="save">Granting…</span>
                </x-button>
            </div>
        </form>
    @endif
</div>
