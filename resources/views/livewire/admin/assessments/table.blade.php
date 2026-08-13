<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1>Assessments</h1>
            <p class="mt-1 text-sm text-neutral-500">Every quiz and test across the catalogue.</p>
        </div>
    </div>

    <div class="mb-5 flex flex-wrap items-center gap-4">
        <x-input wire:model.live.debounce.300ms="search" type="search" placeholder="Search by title" class="max-w-xs" />

        <x-select wire:model.live="typeFilter" name="typeFilter" placeholder="All types" class="max-w-xs">
            @foreach (\App\Enums\AssessmentType::cases() as $type)
                <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
            @endforeach
        </x-select>
    </div>

    @if ($assessments->isEmpty())
        <x-empty-state
            title="No assessments yet"
            description="Assessments are created from a quiz lesson's editor or a course's final test section."
        />
    @else
        <x-table caption="Assessments">
            <x-slot:head>
                <th class="px-3 py-2">
                    <button type="button" wire:click="sortBy('title')" class="font-semibold uppercase tracking-wide">Title</button>
                </th>
                <th class="px-3 py-2">Type</th>
                <th class="px-3 py-2">Attached to</th>
                <th class="px-3 py-2">Questions</th>
                <th class="px-3 py-2">Status</th>
            </x-slot:head>

            @foreach ($assessments as $assessment)
                <tr wire:key="assessment-{{ $assessment->id }}" class="cursor-pointer hover:bg-neutral-50" onclick="window.location='{{ route('admin.assessments.builder', $assessment) }}'">
                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $assessment->title }}</td>
                    <td class="px-3 py-2 text-neutral-500">{{ ucfirst($assessment->type->value) }}</td>
                    <td class="px-3 py-2 text-neutral-500">{{ $this->attachPointLabel($assessment) }}</td>
                    <td class="px-3 py-2 text-neutral-500">{{ $assessment->questions_count }}</td>
                    <td class="px-3 py-2">
                        <x-badge :variant="$assessment->is_published ? 'success' : 'neutral'">
                            {{ $assessment->is_published ? 'Published' : 'Draft' }}
                        </x-badge>
                    </td>
                </tr>
            @endforeach

            <x-slot:pagination>
                {{ $assessments->links() }}
            </x-slot:pagination>
        </x-table>
    @endif
</div>
