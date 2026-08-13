@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    @php($user = auth()->user())

    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h1 class="text-xl font-semibold text-neutral-900">Your profile</h1>
            <p class="mt-1 text-sm text-neutral-500">Manage your account details and password.</p>
        </div>

        @if (session('status') === 'password-updated')
            <x-alert variant="success">
                Your password has been updated. You have been signed out of all other devices.
            </x-alert>
        @endif

        <x-card title="Account" description="Your role and status are managed by an administrator.">
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-neutral-500">Name</dt>
                    <dd class="mt-0.5 font-medium text-neutral-900">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Email</dt>
                    <dd class="mt-0.5 font-medium text-neutral-900">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Role</dt>
                    <dd class="mt-0.5"><x-badge variant="brand">{{ $user->role->label() }}</x-badge></dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Status</dt>
                    <dd class="mt-0.5">
                        <x-badge :variant="$user->status->badgeVariant()">{{ $user->status->label() }}</x-badge>
                    </dd>
                </div>
            </dl>

            {{--
                Role and status are displayed but NOT editable here. A user may
                never change their own role or status — UserPolicy refuses it
                even for a super admin (FR-RBAC-08). Rendering them read-only is
                presentation; the policy is the control.
            --}}
        </x-card>

        {{-- Editable details and email, Phase 7. The account card above stays
             read-only: role and status are an administrator's to set. --}}
        <livewire:student.profile-form />

        <x-card title="Change password" description="Choose a strong password you don't use anywhere else.">
            <form method="POST" action="{{ route('user-password.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <x-input
                    label="Current password"
                    name="current_password"
                    type="password"
                    autocomplete="current-password"
                    required
                />

                <x-input label="New password" name="password" type="password" autocomplete="new-password" required />

                <x-input
                    label="Confirm new password"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                />

                <x-alert variant="info">
                    Changing your password signs you out of all other devices.
                </x-alert>

                <x-button type="submit">Update password</x-button>
            </form>
        </x-card>
    </div>
@endsection
