@extends('layouts.admin')

@section('title', 'Administration')
@section('heading', 'Administration')

@section('content')
    {{-- Phase 2 placeholder. Phase 4 replaces this with the real dashboard:
         KPI tiles, recent orders and enrollments, failed webhooks. --}}
    <div class="max-w-2xl space-y-6">
        <x-card title="Signed in as Super Admin">
            <p class="text-sm text-zinc-600">
                Authentication and role-based access control are in place. This area is reachable
                only by an active <span class="font-medium">super_admin</span> account.
            </p>
        </x-card>

        <x-empty-state
            title="The Administrator Area arrives in Phase 4"
            description="Dashboard, student and instructor management, instructor-to-course assignment, settings and the audit log viewer are built next. Course management follows in Phase 5."
        >
            <x-slot:action>
                <x-button :href="route('profile.show')" variant="secondary" size="sm">Your profile</x-button>
            </x-slot:action>
        </x-empty-state>
    </div>
@endsection
