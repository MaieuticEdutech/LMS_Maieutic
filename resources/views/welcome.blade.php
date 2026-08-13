@extends('layouts.public')

@section('title', config('app.name').' — Phase 1 Foundation')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <x-badge variant="brand">Phase 1 &middot; Project Foundation</x-badge>

        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-neutral-900">
            {{ config('app.name') }}
        </h1>

        <p class="mt-3 text-neutral-600">
            The application skeleton is running. This placeholder proves the asset pipeline,
            the layout system and the base component library are wired correctly. It is
            replaced by the public course catalogue in Phase 5.
        </p>

        <x-card class="mt-8" title="Environment" description="Verified at Phase 1 installation.">
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-4 sm:block">
                    <dt class="text-neutral-500">Laravel</dt>
                    <dd class="font-medium text-neutral-900">{{ app()->version() }}</dd>
                </div>
                <div class="flex justify-between gap-4 sm:block">
                    <dt class="text-neutral-500">PHP</dt>
                    <dd class="font-medium text-neutral-900">{{ PHP_VERSION }}</dd>
                </div>
                <div class="flex justify-between gap-4 sm:block">
                    <dt class="text-neutral-500">Database</dt>
                    <dd class="font-medium text-neutral-900">{{ config('database.default') }}</dd>
                </div>
                <div class="flex justify-between gap-4 sm:block">
                    <dt class="text-neutral-500">Environment</dt>
                    <dd class="font-medium text-neutral-900">{{ app()->environment() }}</dd>
                </div>
            </dl>

            <x-slot:footer>
                <div class="flex flex-wrap items-center gap-3">
                    <x-button :href="route('health')" variant="secondary" size="sm">
                        Health check
                    </x-button>
                    <span class="text-xs text-neutral-500">
                        Reports database, cache and content-storage reachability.
                    </span>
                </div>
            </x-slot:footer>
        </x-card>

        <x-alert variant="info" class="mt-6" title="No features yet — and that is correct.">
            Phase 1 delivers foundation only: tooling, configuration, layouts and quality gates.
            Authentication arrives in Phase 2, the domain schema in Phase 3. No phase begins
            before the previous one meets its Definition of Done.
        </x-alert>
    </div>
@endsection
