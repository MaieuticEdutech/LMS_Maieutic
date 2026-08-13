{{--
    Email log (FR-MAIL-10).

    The screen someone opens when a student says "I never got the email". It
    answers that in one read: did it leave, when, and if not, why. Everything
    here serves that question — the failure tile is a button because finding
    failures is what people come here to do.

    Follows docs/UI-GUIDE.md: mono eyebrow labels, serif figures, soft-tint
    status pills, brand teal for the one interactive accent.
--}}
<div>
    {{-- ══ SUMMARY ══ Totals, not filtered counts: a "failed" figure that
         changed when you filtered to failures would tell you nothing. --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <x-stat-tile label="Sent" :value="number_format($summary['sent'])" />
        <x-stat-tile label="Queued" :value="number_format($summary['queued'])" />

        @if ($summary['failed'] > 0)
            {{-- A real button, not a tinted tile: during an incident this is
                 the only view anyone wants, and making them find it in a
                 dropdown is a small cruelty. --}}
            <button type="button" wire:click="showFailuresOnly"
                    class="rounded-card border border-red-200 bg-red-50 p-5 text-left transition-colors hover:bg-red-100">
                <p class="eyebrow text-red-600">Failed</p>
                <p class="mt-2 font-serif text-3xl font-semibold tracking-[-0.015em] text-red-600">
                    {{ number_format($summary['failed']) }}
                </p>
                <p class="mt-1 text-xs text-red-600/80">Show these only</p>
            </button>
        @else
            <x-stat-tile label="Failed" value="0" />
        @endif
    </div>

    {{-- ══ FILTERS ══ --}}
    <div class="mb-4 flex flex-wrap items-end gap-4">
        <x-input wire:model.live.debounce.300ms="search" type="search"
                 placeholder="Search recipient, subject or type" class="max-w-xs" />

        <x-select wire:model.live="statusFilter" name="statusFilter" label="Status"
                  placeholder="All statuses" class="max-w-xs">
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}">{{ ucfirst($status->value) }}</option>
            @endforeach
        </x-select>
    </div>

    @if ($entries->isEmpty())
        <x-empty-state
            title="No emails logged yet"
            description="Every outbound message is recorded here as it is sent — activation links, enrollment notices, assessment results and password changes, with the delivery outcome of each."
        />
    @else
        <x-table caption="Outbound email log">
            <x-slot:head>
                <th class="px-3 py-2">
                    <button type="button" wire:click="sortBy('created_at')" class="font-semibold uppercase tracking-wide">When</button>
                </th>
                <th class="px-3 py-2">Recipient</th>
                <th class="px-3 py-2">Subject</th>
                <th class="px-3 py-2">Type</th>
                <th class="px-3 py-2">
                    <button type="button" wire:click="sortBy('status')" class="font-semibold uppercase tracking-wide">Status</button>
                </th>
            </x-slot:head>

            @foreach ($entries as $entry)
                <tr wire:key="email-log-{{ $entry->id }}">
                    <td class="whitespace-nowrap px-3 py-2 text-neutral-500">{{ $entry->created_at?->format('d M Y, H:i') }}</td>
                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $entry->to_email }}</td>
                    <td class="max-w-sm px-3 py-2 text-neutral-500">{{ $entry->subject }}</td>

                    {{-- Class basename only. The namespace is noise in a table
                         and the full name is one hover away in the title. --}}
                    <td class="px-3 py-2 font-mono text-xs text-neutral-600" title="{{ $entry->mailable }}">
                        {{ class_basename($entry->mailable) }}
                    </td>

                    <td class="px-3 py-2">
                        @if ($entry->status === \App\Enums\EmailStatus::Sent)
                            <x-badge variant="success">Sent</x-badge>
                        @elseif ($entry->status === \App\Enums\EmailStatus::Queued)
                            <x-badge variant="warning">Queued</x-badge>
                        @else
                            <x-badge variant="danger">Failed</x-badge>
                        @endif

                        {{-- The error sits with its row, collapsed. A failure
                             is useless without its reason, and a reason in a
                             separate screen is a reason nobody reads. --}}
                        @if ($entry->error)
                            <details class="mt-1">
                                <summary class="cursor-pointer text-xs text-teal-600 hover:underline">Why it failed</summary>
                                <pre class="mt-1 max-w-md overflow-x-auto whitespace-pre-wrap text-xs text-neutral-500">{{ $entry->error }}</pre>
                            </details>
                        @endif
                    </td>
                </tr>
            @endforeach

            <x-slot:pagination>
                {{ $entries->links() }}
            </x-slot:pagination>
        </x-table>
    @endif
</div>
