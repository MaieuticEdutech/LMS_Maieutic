{{--
    Admin enrolments — list and lifecycle controls (Phase 6, FR-ENR-07/08).

    Built against docs/UI-GUIDE.md and the `enrollments` section of
    `sample ui/ui/Super Admin.dc.html`. The reference's inline styles are
    deliberately NOT copied — every value here is a token utility (§4 rule 1).

    Departures from the reference, and why:
      - It shows a progress column. `lesson_progress` has no aggregate to read
        yet (Phase 9 builds it), so a percentage here would be invented. The
        column is left out rather than filled with a placeholder number.
      - It has no lifecycle controls at all. FR-ENR-08 requires suspend,
        reinstate and revoke, so they are added here in the reference's idiom.
--}}
<div>
    {{-- Page header. Serif display against a mono eyebrow is the type
         contrast the system is built on (§6). --}}
    <div class="mb-6">
        <p class="font-mono text-[11px] uppercase tracking-[0.14em] text-neutral-500">Administration</p>
        <h1 class="mt-1 font-serif text-3xl font-semibold tracking-tight text-neutral-900">Enrolments</h1>
        <p class="mt-1 text-sm text-neutral-500">
            {{ number_format($summary['total']) }} {{ Str::plural('enrolment', $summary['total']) }}
            @if ($summary['total'] > 0)
                &middot; {{ number_format($summary['active']) }} with active access
            @endif
        </p>
    </div>

    @if (session('status'))
        <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    {{-- Filters. Quiet by default (§8): no heavy filled blocks. --}}
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <x-input
            wire:model.live.debounce.300ms="search"
            type="search"
            name="search"
            label="Search"
            placeholder="Student name, email or course"
            class="w-full sm:max-w-xs"
        />

        <x-select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="All statuses" class="w-full sm:w-44">
            @foreach ($statusOptions as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </x-select>

        <x-select wire:model.live="sourceFilter" name="sourceFilter" label="Source" placeholder="All sources" class="w-full sm:w-44">
            @foreach ($sourceOptions as $source)
                <option value="{{ $source->value }}">{{ $source->label() }}</option>
            @endforeach
        </x-select>

        @if ($courseOptions !== [])
            <x-select wire:model.live="courseFilter" name="courseFilter" label="Course" placeholder="All courses" class="w-full sm:w-56">
                @foreach ($courseOptions as $id => $title)
                    <option value="{{ $id }}">{{ $title }}</option>
                @endforeach
            </x-select>
        @endif

        <div class="ms-auto">
            <x-button variant="primary" :href="route('admin.enrollments.create')">Grant access</x-button>
        </div>
    </div>

    {{-- Loading state (§11): skeleton rows matching the final layout, so the
         table does not shift when results arrive. --}}
    <div wire:loading.delay.long class="space-y-2" aria-hidden="true">
        @for ($i = 0; $i < 5; $i++)
            <div class="h-12 animate-pulse rounded-control bg-neutral-100"></div>
        @endfor
    </div>

    <div wire:loading.delay.long.remove>
        @if ($enrollments->isEmpty())
            <x-empty-state
                :title="$search !== '' || $statusFilter !== '' || $sourceFilter !== '' || $courseFilter !== '' ? 'No enrolments match those filters' : 'No enrolments yet'"
                :description="$search !== '' || $statusFilter !== '' || $sourceFilter !== '' || $courseFilter !== ''
                    ? 'Try a different search term, or clear the filters to see every enrolment.'
                    : 'Enrolments appear here when a student buys a course, or when you grant access directly.'"
            >
                <x-slot:action>
                    @if ($search !== '' || $statusFilter !== '' || $sourceFilter !== '' || $courseFilter !== '')
                        <x-button variant="secondary" wire:click="resetTableFilters">Clear filters</x-button>
                    @else
                        <x-button variant="primary" :href="route('admin.enrollments.create')">Grant access</x-button>
                    @endif
                </x-slot:action>
            </x-empty-state>
        @else
            <x-table caption="Enrolments">
                <x-slot:head>
                    <th scope="col" class="px-3 py-2">
                        <button type="button" wire:click="sortBy('user_id')" class="uppercase tracking-[0.1em]">Student</button>
                    </th>
                    <th scope="col" class="px-3 py-2">Course</th>
                    <th scope="col" class="px-3 py-2">
                        <button type="button" wire:click="sortBy('enrolled_at')" class="uppercase tracking-[0.1em]">Enrolled</button>
                    </th>
                    <th scope="col" class="px-3 py-2">Source</th>
                    <th scope="col" class="px-3 py-2">Access</th>
                    <th scope="col" class="px-3 py-2 text-right">Actions</th>
                </x-slot:head>

                @foreach ($enrollments as $enrollment)
                    <tr wire:key="enrollment-{{ $enrollment->id }}" class="transition-colors hover:bg-neutral-50">
                        <td class="px-3 py-2.5">
                            <div class="font-medium text-neutral-900">{{ $enrollment->user?->name ?? 'Deleted student' }}</div>
                            {{-- Truncated with a title so a long address stays
                                 readable on hover rather than breaking the grid. --}}
                            <div class="max-w-[18ch] truncate text-xs text-neutral-500 sm:max-w-[28ch]" title="{{ $enrollment->user?->email }}">
                                {{ $enrollment->user?->email }}
                            </div>
                        </td>

                        <td class="px-3 py-2.5">
                            <span class="block max-w-[24ch] truncate text-neutral-800 sm:max-w-[36ch]" title="{{ $enrollment->course?->title }}">
                                {{ $enrollment->course?->title ?? 'Deleted course' }}
                            </span>
                        </td>

                        <td class="px-3 py-2.5 whitespace-nowrap text-neutral-500">
                            {{ $enrollment->enrolled_at?->format('d M Y') ?? '—' }}
                        </td>

                        <td class="px-3 py-2.5 text-neutral-500">{{ $enrollment->source->label() }}</td>

                        <td class="px-3 py-2.5">
                            {{-- Label always present beside the colour: meaning
                                 is never carried by colour alone (§12). --}}
                            <x-badge :variant="$enrollment->status->badgeVariant()">{{ $enrollment->status->label() }}</x-badge>

                            @if ($enrollment->expires_at !== null && $enrollment->status->isAccessGranting())
                                <span class="mt-1 block text-xs text-neutral-500">
                                    Until {{ $enrollment->expires_at->format('d M Y') }}
                                </span>
                            @endif
                        </td>

                        <td class="px-3 py-2.5">
                            <div class="flex justify-end gap-1">
                                @if ($enrollment->status === \App\Enums\EnrollmentStatus::Suspended)
                                    <x-button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="reinstate({{ $enrollment->id }})"
                                        wire:loading.attr="disabled"
                                    >Reinstate</x-button>
                                @elseif ($enrollment->status->isAccessGranting())
                                    <x-button variant="ghost" size="sm" wire:click="confirmSuspend({{ $enrollment->id }})">Suspend</x-button>
                                @endif

                                @if ($enrollment->status !== \App\Enums\EnrollmentStatus::Refunded)
                                    <x-button variant="ghost" size="sm" class="text-red-600 hover:bg-red-50" wire:click="confirmRevoke({{ $enrollment->id }})">Revoke</x-button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach

                <x-slot:pagination>
                    {{ $enrollments->links() }}
                </x-slot:pagination>
            </x-table>
        @endif
    </div>

    {{-- ══════════════ SUSPEND ══════════════ --}}
    <x-modal name="suspend-enrollment" title="Suspend access" maxWidth="lg">
        <form wire:submit="suspend" class="space-y-4">
            <p class="text-sm text-neutral-800">
                @if ($target)
                    {{ $target->user?->name }} will lose access to
                    <span class="font-medium text-neutral-900">{{ $target->course?->title }}</span>
                    until you reinstate it. Nothing is deleted, and their progress is kept.
                @endif
            </p>

            <x-textarea
                wire:model="reason"
                name="reason"
                label="Reason"
                hint="Recorded in the audit log."
                :rows="3"
                required
            />

            <x-slot:footer>
                <x-button variant="secondary" type="button" wire:click="cancelAction" x-on:click="open = false">Cancel</x-button>
                <x-button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="suspend">
                    <span wire:loading.remove wire:target="suspend">Suspend access</span>
                    <span wire:loading wire:target="suspend">Suspending…</span>
                </x-button>
            </x-slot:footer>
        </form>
    </x-modal>

    {{-- ══════════════ REVOKE ══════════════
         FR-ADM-17: destructive, so it takes a TYPED confirmation, not a click.
    --}}
    <x-modal name="revoke-enrollment" title="Revoke access" maxWidth="lg">
        <form wire:submit="revoke" class="space-y-4">
            @if ($target)
                <x-alert variant="warning">
                    {{ $target->user?->name }} will immediately lose access to
                    <span class="font-medium">{{ $target->course?->title }}</span>,
                    and will be emailed. Restoring access afterwards means granting it again.
                </x-alert>
            @endif

            <x-textarea
                wire:model="reason"
                name="reason"
                label="Reason"
                hint="Recorded against the student's record, and shown to whoever reviews this later."
                :rows="3"
                required
            />

            {{-- The status stored differs: `refunded` vs `expired`, so the
                 commercial history stays legible for Phase 13's reporting.
                 Only the administrator knows which happened. --}}
            <x-checkbox
                wire:model="refunded"
                name="refunded"
                label="Money was refunded"
                hint="Records this as a refund rather than an ordinary revocation."
            />

            <x-input
                wire:model="confirmation"
                name="confirmation"
                label="Type {{ $confirmWord }} to confirm"
                autocomplete="off"
                required
            />

            <x-slot:footer>
                <x-button variant="secondary" type="button" wire:click="cancelAction" x-on:click="open = false">Cancel</x-button>
                <x-button variant="danger" type="submit" wire:loading.attr="disabled" wire:target="revoke">
                    <span wire:loading.remove wire:target="revoke">Revoke access</span>
                    <span wire:loading wire:target="revoke">Revoking…</span>
                </x-button>
            </x-slot:footer>
        </form>
    </x-modal>
</div>
