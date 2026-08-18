{{-- Course progress report (FR-RPT-03). A funnel: the gaps between the
     columns are the diagnostic, not any single figure. --}}
<div>
    @include('livewire.reports.partials.tabs')

    @include('livewire.reports.partials.filter-bar')

    @if ($rows->isEmpty())
        <x-empty-state title="No enrolments in this period" description="Widen the date range, or clear it to see everything." />
    @else
        <x-table>
            <x-slot:head>
                <th class="px-3 py-2">Course</th>
                <th class="px-3 py-2 text-right">Enrolled</th>
                <th class="px-3 py-2 text-right">Started</th>
                <th class="px-3 py-2 text-right">In progress</th>
                <th class="px-3 py-2 text-right">Completed</th>
                <th class="px-3 py-2">Average</th>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr wire:key="cp-{{ $loop->index }}">
                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $row['course'] }}</td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['enrolled'] }}</td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['started'] }}</td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['in_progress'] }}</td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['completed'] }}</td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2.5">
                            <x-progress-bar :value="$row['average']" class="w-28" />
                            <span class="font-mono text-xs font-semibold tabular-nums text-neutral-700">{{ $row['average'] }}%</span>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</div>
