{{--
    Queue health (phases.md Phase 11).

    Two questions, in the order an operator asks them: is the queue moving,
    and what is stuck. Depth alone cannot answer the first — a hundred jobs
    queued a second ago is a busy system and one job waiting an hour is a
    stopped worker — so the age of the oldest job sits beside the counts.

    Follows docs/UI-GUIDE.md: mono eyebrows, serif figures, soft-tint status,
    danger red reserved for the thing that genuinely is one.
--}}
<div class="space-y-6">

    @if (session('status'))
        <x-alert variant="info">{{ session('status') }}</x-alert>
    @endif

    {{-- ══ PENDING ══ --}}
    <section aria-labelledby="pending-heading">
        <h2 id="pending-heading" class="text-lg">Pending work</h2>

        @if ($pending === null)
            {{-- Honest rather than reassuring. The counts come from the `jobs`
                 table, which only exists on the database driver; a panel
                 reporting "0 pending" against a backed-up Redis queue would be
                 worse than one admitting it cannot see. --}}
            <div class="mt-3">
                <x-empty-state
                    title="Queue depth is not readable on this driver"
                    description="Pending counts are read from the database queue table. This environment uses a different queue driver, so depth has to come from that system's own tooling. Failed jobs below are still complete and accurate."
                />
            </div>
        @else
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($pending as $queue => $count)
                    <x-stat-tile :label="$queue" :value="number_format($count)" />
                @endforeach
            </div>

            <p class="mt-3 text-sm text-neutral-500">
                @if ($oldestSeconds === null)
                    Nothing is waiting. Every queue is empty.
                @elseif ($oldestSeconds < 60)
                    The oldest waiting job arrived {{ $oldestSeconds }} {{ Str::plural('second', $oldestSeconds) }} ago.
                @else
                    {{-- Past a minute this stops being a throughput figure and
                         starts being a question about whether a worker is
                         running at all, so it is said in those terms. --}}
                    The oldest waiting job has been queued for
                    <strong class="text-neutral-800">{{ \Carbon\CarbonInterval::seconds($oldestSeconds)->cascade()->forHumans(short: true) }}</strong>.
                    If that keeps climbing, no worker is draining this queue.
                @endif
            </p>
        @endif
    </section>

    {{-- ══ FAILED ══ --}}
    <section aria-labelledby="failed-heading" class="space-y-3">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 id="failed-heading" class="text-lg">Failed jobs</h2>

            @if ($failedCount > 0)
                <x-badge variant="danger">{{ number_format($failedCount) }} {{ Str::plural('failure', $failedCount) }}</x-badge>
            @endif
        </div>

        @if ($failed === [])
            <x-empty-state
                title="Nothing has failed"
                description="A job that exhausts its retries appears here with the reason it failed, and can be pushed back onto its queue or discarded."
            />
        @else
            {{-- Said plainly, because the consequence is not obvious from a
                 table of class names: these are promises the system made and
                 did not keep. --}}
            <p class="max-w-[68ch] text-sm text-neutral-500">
                Each of these has exhausted its retries and will not run on its own. A mail job here means
                somebody never received an email; a progress job means a percentage is wrong and will stay wrong.
                Every job in this system is safe to retry.
            </p>

            <x-table caption="Failed jobs">
                <x-slot:head>
                    <th class="px-3 py-2">Failed at</th>
                    <th class="px-3 py-2">Job</th>
                    <th class="px-3 py-2">Queue</th>
                    <th class="px-3 py-2">Reason</th>
                    <th class="px-3 py-2 text-right">Action</th>
                </x-slot:head>

                @foreach ($failed as $job)
                    <tr wire:key="failed-{{ $job['uuid'] }}">
                        <td class="whitespace-nowrap px-3 py-2 text-neutral-500">{{ $job['failed_at'] }}</td>
                        <td class="px-3 py-2 font-mono text-xs text-neutral-800" title="{{ $job['job'] }}">
                            {{ class_basename($job['job']) }}
                        </td>
                        <td class="px-3 py-2 text-neutral-500">{{ $job['queue'] }}</td>
                        <td class="max-w-md px-3 py-2 text-xs break-words text-neutral-500">{{ $job['exception'] }}</td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-end gap-2">
                                <x-button wire:click="retry('{{ $job['uuid'] }}')" size="sm" variant="secondary"
                                          wire:loading.attr="disabled">
                                    Retry
                                </x-button>

                                {{-- Confirmed, because it is the one control
                                     here that destroys work rather than
                                     repeating it. --}}
                                <button type="button"
                                        wire:click="forget('{{ $job['uuid'] }}')"
                                        wire:confirm="Discard this job? It will not run, and whatever it was going to do will not happen."
                                        class="text-sm text-neutral-500 underline-offset-4 transition-colors hover:text-red-600 hover:underline">
                                    Discard
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </section>
</div>
