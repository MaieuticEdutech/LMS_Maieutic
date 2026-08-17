{{--
    STUDENT SHELL — the layout every signed-in student screen sits under.

    The header itself lives in `partials/student-header.blade.php`, because
    `layouts.public` needs the same one: the catalogue is a public page that
    students also browse, and a student clicking "Explore" must not watch their
    navigation disappear.

    NFR-UX-02: the player must be fully usable on a mobile device, so the
    header collapses (search hides, gutters narrow) rather than horizontally
    scrolling.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body data-lms-layout="student" class="flex min-h-full flex-col bg-neutral-50 font-sans text-neutral-800 antialiased">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-sm focus:bg-teal-600 focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    @include('partials.student-header')

    <main id="main" class="flex-1">
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    @livewireScripts
</body>
</html>
