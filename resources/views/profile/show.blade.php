@extends('layouts.student')

@section('title', 'Profile')

@section('content')
    {{--
        Profile: an identity card on the left at 300px, the editable cards
        stacked on the right.

        The mockup's left card also shows "42h learned" and "2 certificates".
        Neither exists — nothing records watch time as hours and there is no
        certificate model anywhere in this codebase — so that panel shows the
        two figures the system can stand behind instead.

        WIDTH: max-w-content, the site's one page-content token
        (--container-content in app.css, 1240px) — declared for exactly this
        but never actually applied anywhere until now. This page used a
        hand-picked 960px before, which on a wide desktop left roughly a
        third of the screen empty on each side. The header above it
        (student-header.blade.php) is full-bleed, not capped, so this is not
        about lining up with it — it is about using the width the design
        system already settled on instead of a page-specific guess.
    --}}
    <div class="mx-auto w-full max-w-content px-5 pb-24 pt-12 lg:px-10">

        <h1 class="mb-10 font-serif text-[40px]/[1.1] font-medium">Profile</h1>

        @if (session('status') === 'password-updated')
            <div class="mb-6">
                <x-alert variant="success">
                    Your password has been updated. You have been signed out of all other devices.
                </x-alert>
            </div>
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-[300px_1fr]">

            {{-- ══ IDENTITY ══ its own Livewire component, not inline markup —
                 see ProfileIdentity's class docblock for why: it has to update
                 the moment Upload Documents (elsewhere on this page) changes
                 the photo, and this static page only ever renders once. --}}
            <livewire:student.profile-identity />

            {{-- ══ EDITABLE ══ --}}
            <div class="flex flex-col gap-6">
                {{-- Details and email, Phase 7. --}}
                <livewire:student.profile-form />

                {{-- Upload Documents — its own card, its own component: see
                     ProfileDocumentsForm's class docblock. --}}
                <livewire:student.profile-documents-form />

                {{-- An x-card like the three above it, not a hand-built card:
                     this was the one section on the page with its own sans
                     heading and its own padding. The header slot carries the
                     same serif/teal-600 title every other section uses. --}}
                <x-card>
                    <x-slot:header>
                        <h2 class="text-lg text-teal-600">Password</h2>
                        <p class="mt-1 text-sm text-neutral-500">Choose a strong password you don't use anywhere else.</p>
                    </x-slot:header>

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
