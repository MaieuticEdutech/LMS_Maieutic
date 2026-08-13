<div class="max-w-2xl space-y-6">
    <x-card>
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900">{{ $instructor->name }}</h2>
                <p class="text-sm text-neutral-500">{{ $instructor->email }}</p>
                @if ($instructor->phone)
                    <p class="text-sm text-neutral-500">{{ $instructor->phone }}</p>
                @endif
                @if ($instructor->instructorProfile?->headline)
                    <p class="mt-2 text-sm font-medium text-neutral-700">{{ $instructor->instructorProfile->headline }}</p>
                @endif
                @if ($instructor->instructorProfile?->bio)
                    <p class="mt-1 text-sm text-neutral-500">{{ $instructor->instructorProfile->bio }}</p>
                @endif
            </div>
            <x-badge :variant="$instructor->status->badgeVariant()">{{ $instructor->status->label() }}</x-badge>
        </div>

        <div class="mt-4 flex flex-wrap gap-2 border-t border-neutral-200 pt-4">
            @can('update', $instructor)
                <x-button :href="route('admin.instructors.edit', $instructor)" variant="secondary" size="sm" wire:navigate>Edit</x-button>

                @if ($instructor->status === \App\Enums\UserStatus::PendingActivation)
                    <x-button wire:click="resendActivation" variant="secondary" size="sm">Resend activation link</x-button>
                @else
                    <x-button wire:click="forcePasswordReset" variant="secondary" size="sm">Force password reset</x-button>
                @endif
            @endcan

            @can('changeStatus', $instructor)
                @foreach ($assignableStatuses as $status)
                    @if ($status !== $instructor->status)
                        <x-button wire:click="changeStatus('{{ $status->value }}')" variant="secondary" size="sm">
                            Set {{ $status->label() }}
                        </x-button>
                    @endif
                @endforeach
            @endcan

            @can('delete', $instructor)
                <x-button x-on:click="$dispatch('open-modal', 'delete-instructor')" variant="danger" size="sm">Delete</x-button>
            @endcan
        </div>
    </x-card>

    @can('delete', $instructor)
        <div x-data="{ confirmation: '' }">
            <x-modal name="delete-instructor" title="Delete instructor">
                <p class="text-sm text-neutral-600">
                    This soft-deletes <span class="font-medium">{{ $instructor->email }}</span>. Their audit
                    history and authored content are preserved. Type the instructor's email address to confirm.
                </p>

                <x-input x-model="confirmation" class="mt-3" placeholder="{{ $instructor->email }}" />

                <x-slot:footer>
                    <x-button x-on:click="$dispatch('close-modal', 'delete-instructor')" variant="secondary" size="sm">Cancel</x-button>
                    <button
                        type="button"
                        wire:click="delete"
                        x-bind:disabled="confirmation !== '{{ $instructor->email }}'"
                        class="inline-flex items-center justify-center rounded-control bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Delete instructor
                    </button>
                </x-slot:footer>
            </x-modal>
        </div>
    @endcan

    @livewire('admin.course-instructor-assignment', ['instructor' => $instructor])
</div>
