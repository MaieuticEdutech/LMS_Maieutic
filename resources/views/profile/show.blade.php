@extends('layouts.student')

@section('title', 'Profile')

@section('content')
    @php($user = auth()->user())

    {{--
        Profile, following the mockup: a 960px column, an identity card on the
        left at 300px, and the editable cards stacked on the right.

        The mockup's left card also shows "42h learned" and "2 certificates".
        Neither exists — nothing records watch time as hours and there is no
        certificate model anywhere in this codebase — so that panel shows the
        two figures the system can stand behind instead.
    --}}
    <div class="mx-auto w-full max-w-[960px] px-5 pb-24 pt-12 lg:px-10">

        <h1 class="mb-10 font-serif text-[40px]/[1.1] font-medium">Profile</h1>

        @if (session('status') === 'password-updated')
            <div class="mb-6">
                <x-alert variant="success">
                    Your password has been updated. You have been signed out of all other devices.
                </x-alert>
            </div>
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-[300px_1fr]">

            {{-- ══ IDENTITY ══ read-only. Role and status are displayed but NOT
                 editable: a user may never change their own, and UserPolicy
                 refuses it even for a super admin (FR-RBAC-08). Rendering them
                 read-only is presentation; the policy is the control. --}}
            <div class="flex flex-col items-center gap-1.5 rounded-card border border-neutral-200 bg-white p-7 text-center">
                <div class="mb-2 flex h-[76px] w-[76px] items-center justify-center rounded-full bg-teal-600 text-2xl font-semibold text-white"
                     aria-hidden="true">
                    {{ $user->initials() }}
                </div>

                <div class="font-sans text-lg font-semibold tracking-normal text-neutral-900">{{ $user->name }}</div>
                <div class="text-[13.5px] text-neutral-500">{{ $user->email }}</div>

                <div class="text-[12.5px] text-neutral-400">
                    Learning since {{ $user->created_at?->format('F Y') }}
                </div>

                <div class="mt-4 flex w-full items-center justify-center gap-2 border-t border-neutral-100 pt-4">
                    <x-badge variant="brand">{{ $user->role->label() }}</x-badge>
                    <x-badge :variant="$user->status->badgeVariant()">{{ $user->status->label() }}</x-badge>
                </div>
            </div>

            {{-- ══ EDITABLE ══ --}}
            <div class="flex flex-col gap-6">
                {{-- Details and email, Phase 7. --}}
                <livewire:student.profile-form />

                <div class="rounded-card border border-neutral-200 bg-white p-7">
                    <h2 class="font-sans text-base font-semibold tracking-normal text-neutral-900">Password</h2>
                    <p class="mb-4 mt-1.5 text-[13.5px] text-neutral-500">
                        Choose a strong password you don't use anywhere else.
                    </p>

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
                </div>

                {{-- Sign out lives here, as in the mockup — the header carries
                     the avatar that leads to this page rather than a logout
                     button of its own. Red-outlined rather than solid: it is a
                     way out, not the thing the page wants you to do. --}}
                <div class="flex justify-end">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="rounded-sm border border-red-100 bg-white px-5 py-[11px] text-sm font-semibold text-red-600 transition-colors hover:bg-red-50">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
