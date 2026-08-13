@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    {{-- Phase 2 placeholder. Phase 7 replaces this with the real dashboard:
         courses in progress, continue learning, recent results. --}}
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h1 class="text-xl font-semibold text-neutral-900">Dashboard</h1>
            <p class="mt-1 text-sm text-neutral-500">Signed in as a student.</p>
        </div>

        <x-alert variant="info" title="Your account is not course access">
            Having an account does not enrol you in anything. Course access is granted only after a
            payment the backend has independently verified — or by an administrator.
        </x-alert>

        <x-empty-state
            title="You are not enrolled in any courses yet"
            description="Browsing and purchasing courses arrives in Phase 5 and Phase 12. Once you are enrolled, your courses and progress appear here."
        >
            <x-slot:action>
                <x-button :href="route('profile.show')" variant="secondary" size="sm">Your profile</x-button>
            </x-slot:action>
        </x-empty-state>
    </div>
@endsection
