@extends('layouts.instructor')

@section('title', 'Instructor')

@section('content')
    {{-- Phase 2 placeholder. Phase 10 replaces this with the real dashboard,
         scoped strictly to assigned courses. --}}
    <div class="max-w-2xl space-y-6">
        <x-card title="Signed in as Instructor">
            <p class="text-sm text-neutral-600">
                This area is reachable only by an active <span class="font-medium">instructor</span> account.
                From Phase 10 it will show only the courses you are assigned to — and never any
                financial data.
            </p>
        </x-card>

        <x-empty-state
            title="The Instructor Area arrives in Phase 10"
            description="Assigned courses, enrolled students, assessment authoring, results and per-student progress are built after the assessment and progress engines exist."
        >
            <x-slot:action>
                <x-button :href="route('profile.show')" variant="secondary" size="sm">Your profile</x-button>
            </x-slot:action>
        </x-empty-state>
    </div>
@endsection
