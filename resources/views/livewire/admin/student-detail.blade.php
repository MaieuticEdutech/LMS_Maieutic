<div class="max-w-2xl space-y-6">
    <x-card>
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900">{{ $student->name }}</h2>
                <p class="text-sm text-neutral-500">{{ $student->email }}</p>
                @if ($student->phone)
                    <p class="text-sm text-neutral-500">{{ $student->phone }}</p>
                @endif
            </div>
            <x-badge :variant="$student->status->badgeVariant()">{{ $student->status->label() }}</x-badge>
        </div>

        <div class="mt-4 flex flex-wrap gap-2 border-t border-neutral-200 pt-4">
            @can('update', $student)
                <x-button :href="route('admin.students.edit', $student)" variant="secondary" size="sm" wire:navigate>Edit</x-button>

                @if ($student->status === \App\Enums\UserStatus::PendingActivation)
                    <x-button wire:click="resendActivation" variant="secondary" size="sm">Resend activation link</x-button>
                @else
                    <x-button wire:click="forcePasswordReset" variant="secondary" size="sm">Force password reset</x-button>
                @endif
            @endcan

            @can('changeStatus', $student)
                @foreach ($assignableStatuses as $status)
                    @if ($status !== $student->status)
                        <x-button wire:click="changeStatus('{{ $status->value }}')" variant="secondary" size="sm">
                            Set {{ $status->label() }}
                        </x-button>
                    @endif
                @endforeach
            @endcan

            @can('delete', $student)
                <x-button x-on:click="$dispatch('open-modal', 'delete-student')" variant="danger" size="sm">Delete</x-button>
            @endcan
        </div>
    </x-card>

    @can('delete', $student)
        <div x-data="{ confirmation: '' }">
            <x-modal name="delete-student" title="Delete student">
                <p class="text-sm text-neutral-600">
                    This soft-deletes <span class="font-medium">{{ $student->email }}</span>. Their orders and
                    audit history are preserved. Type the student's email address to confirm.
                </p>

                <x-input x-model="confirmation" class="mt-3" placeholder="{{ $student->email }}" />

                <x-slot:footer>
                    <x-button x-on:click="$dispatch('close-modal', 'delete-student')" variant="secondary" size="sm">Cancel</x-button>
                    <button
                        type="button"
                        wire:click="delete"
                        x-bind:disabled="confirmation !== '{{ $student->email }}'"
                        class="inline-flex items-center justify-center rounded-control bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Delete student
                    </button>
                </x-slot:footer>
            </x-modal>
        </div>
    @endcan
</div>
