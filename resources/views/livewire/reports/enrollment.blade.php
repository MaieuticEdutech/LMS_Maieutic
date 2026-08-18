{{-- Enrolment report (FR-RPT-01). The source split is the point — see
     EnrollmentReport's docblock. --}}
<div>
    @include('livewire.reports.partials.filter-bar')

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['Purchases', $totals['purchase']],
            ['Admin grants', $totals['admin_grant']],
            ['Imports', $totals['import']],
            ['Total', $totals['total']],
        ] as [$label, $value])
            <div class="rounded-card border border-neutral-200 bg-white px-[22px] py-5">
                <div class="font-serif text-[32px]/none font-medium text-neutral-900">{{ number_format($value) }}</div>
                <div class="mt-2 text-[13px] font-medium text-neutral-500">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    @if ($rows->isEmpty())
        <x-empty-state title="No enrolments in this period" description="Widen the date range, or clear it to see everything." />
    @else
        <x-table>
            <x-slot:head>
                <th class="px-3 py-2">Course</th>
                <th class="px-3 py-2 text-right">Purchases</th>
                <th class="px-3 py-2 text-right">Admin grants</th>
                <th class="px-3 py-2 text-right">Imports</th>
                <th class="px-3 py-2 text-right">Total</th>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr wire:key="enr-{{ $loop->index }}">
                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $row['course'] }}</td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['purchase'] }}</td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['admin_grant'] }}</td>
                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $row['import'] }}</td>
                    <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums">{{ $row['total'] }}</td>
                </tr>
            @endforeach
        </x-table>

        @if ($periods->isNotEmpty())
            <h2 class="mb-3 mt-10 font-serif text-[22px] font-medium">By month</h2>

            <x-table>
                <x-slot:head>
                    <th class="px-3 py-2">Month</th>
                    <th class="px-3 py-2 text-right">Enrolments</th>
                </x-slot:head>

                @foreach ($periods as $period)
                    <tr wire:key="per-{{ $period['period'] }}">
                        <td class="px-3 py-2 font-mono">{{ $period['period'] }}</td>
                        <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $period['total'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    @endif
</div>
