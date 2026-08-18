{{-- Student report (FR-RPT-05). "Last activity" is the actionable column —
     the rest is context for the conversation it prompts. --}}
<div>
    @include('livewire.reports.partials.tabs')

    @include('livewire.reports.partials.filter-bar')

    <div class="mb-6 -mt-2">
        <x-input wire:model.live.debounce.300ms="search" type="search" name="search"
                 placeholder="Search by name or email" class="max-w-xs" />
    </div>

    @if ($rows->isEmpty())
        <x-empty-state title="No students match" description="Widen the date range or clear the search." />
    @else
        <x-table>
            <x-slot:head>
                <th class="px-3 py-2">Student</th>
                <th class="px-3 py-2 text-right">Enrolments</th>
                <th class="px-3 py-2">Average progress</th>
                <th class="px-3 py-2 text-right">Completed</th>
                <th class="px-3 py-2 text-right">Attempts</th>
                <th class="px-3 py-2 text-right">Average score</th>
                <th class="px-3 py-2">Last activity</th>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr wire:key="stu-{{ $loop->index }}">
                    <td class="px-3 py-2">
                        <div class="font-medium text-neutral-900">{{ $row['student'] }}</div>
                        <div class="text-xs text-neutral-500">{{ $row['email'] }}</div>
                    </td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['enrollments'] }}</td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2.5">
                            <x-progress-bar :value="$row['average_progress']" class="w-24" />
                            <span class="font-mono text-xs font-semibold tabular-nums text-neutral-700">{{ $row['average_progress'] }}%</span>
                        </div>
                    </td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['completed'] }}</td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['attempts'] }}</td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">
                        {{-- An em dash, not 0%: no graded attempt means no
                             average, and a zero reads as having failed. --}}
                        {{ $row['average_score'] === null ? '—' : $row['average_score'].'%' }}
                    </td>
                    <td class="px-3 py-2 text-neutral-500">{{ $row['last_activity'] }}</td>
                </tr>
            @endforeach
        </x-table>
    @endif
</div>
