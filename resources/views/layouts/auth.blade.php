{{--
    AUTH LAYOUT — login, register, password reset, verification, activation.

    Every Fortify screen renders through this (C-06, ADR-013). Fortify ships
    no markup of its own; the LMS owns the entire interface.

    Organisation name comes from BrandingService, not config or a hardcoded
    string, so V2 can make it per-organisation without touching this file
    (rule S-1).
--}}
@php($branding = app(\App\Services\Settings\BrandingService::class))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Sign in') &middot; {{ $branding->organisationName() }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col justify-center bg-zinc-50 px-4 py-12 text-zinc-900 antialiased">
    <main class="mx-auto w-full max-w-md">
        <div class="text-center">
            <a href="{{ url('/') }}" class="text-xl font-semibold text-zinc-900">
                {{ $branding->organisationName() }}
            </a>
            @hasSection('heading')
                <h1 class="mt-6 text-lg font-semibold text-zinc-900">@yield('heading')</h1>
            @endif
            @hasSection('subheading')
                <p class="mt-1 text-sm text-zinc-500">@yield('subheading')</p>
            @endif
        </div>

        {{-- Status messages (e.g. "reset link sent", "account ready"). --}}
        @if (session('status'))
            <div class="mt-6">
                <x-alert variant="success">{{ session('status') }}</x-alert>
            </div>
        @endif

        {{--
            Validation errors are also rendered inline against each field
            (NFR-UX-05). This summary exists for screen-reader users, who
            benefit from hearing the count up front rather than discovering
            errors field by field.
        --}}
        @if ($errors->any())
            <div class="mt-6">
                <x-alert variant="danger" :title="trans_choice('There is :count problem with your submission.|There are :count problems with your submission.', $errors->count())">
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            </div>
        @endif

        <div class="mt-6 rounded-card bg-white px-6 py-7 shadow-sm ring-1 ring-zinc-200">
            @yield('content')
        </div>

        @hasSection('footer')
            <p class="mt-6 text-center text-sm text-zinc-500">@yield('footer')</p>
        @endif
    </main>
</body>
</html>
