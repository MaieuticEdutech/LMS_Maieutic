{{-- Assessment report (FR-RPT-04). Expanding a row shows the per-question
     correct rate, weakest first — the column that finds a broken question
     rather than a weak cohort. --}}
<div>
    @include('livewire.reports.partials.tabs')

    @include('livewire.reports.partials.filter-bar')

    @if ($rows->isEmpty())
        <x-empty-state title="No graded attempts in this period"
                       description="Assessments appear here once a student has submitted one and it has been graded." />
    @else
        <x-table>
            <x-slot:head>
                <th class="px-3 py-2">Assessment</th>
                <th class="px-3 py-2">Course</th>
                <th class="px-3 py-2 text-right">Attempts</th>
                <th class="px-3 py-2 text-right">Average</th>
                <th class="px-3 py-2 text-right">Pass rate</th>
                <th class="px-3 py-2"><span class="sr-only">Questions</span></th>
            </x-slot:head>

            @foreach ($rows as $row)
                @php($id = $row['id'] ?? null)
                <tr wire:key="asm-{{ $loop->index }}">
                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $row['assessment'] }}</td>
                    <td class="px-3 py-2 text-neutral-500">{{ $row['course'] }}</td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['attempts'] }}</td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['average'] }}%</td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['pass_rate'] }}%</td>
                    <td class="px-3 py-2 text-right">
                        @if ($id !== null)
                            <button type="button" wire:click="toggle({{ $id }})" class="text-teal-600 hover:underline">
                                {{ $expandedId === $id ? 'Hide questions' : 'Questions' }}
                            </button>
                        @endif
                    </td>
                </tr>

                @if ($id !== null && $expandedId === $id && $questions->isNotEmpty())
                    <tr wire:key="asm-q-{{ $id }}">
                        <td colspan="6" class="bg-neutral-50 px-3 py-4">
                            <p class="mb-3 font-mono text-[11px] uppercase tracking-[0.1em] text-neutral-500">
                                Per question · weakest first
                            </p>

                            <div class="space-y-2">
                                @foreach ($questions as $q)
                                    <div class="flex items-center gap-4">
                                        <span class="min-w-0 flex-1 truncate text-[13.5px] text-neutral-800">{{ $q['question'] }}</span>
                                        <x-progress-bar :value="(int) $q['correct_rate']" class="w-32" />
                                        <span class="w-16 text-right font-mono text-xs font-semibold tabular-nums text-neutral-700">{{ $q['correct_rate'] }}%</span>
                                        <span class="w-24 text-right text-xs text-neutral-500">{{ $q['correct' ] }}/{{ $q['answered'] }} correct</span>
                                    </div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endif
            @endforeach
        </x-table>
    @endif
</div>
