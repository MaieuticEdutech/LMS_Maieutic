@extends('layouts.auth')

@section('title', 'Sign in')
@section('heading', 'Sign in to your account')

@section('content')
    {{--
        Posts to Fortify's /login route. The credential check AND the account
        status check happen together inside Fortify::authenticateUsing(), so a
        suspended or unactivated account never establishes a session
        (architecture.md §7.1.1).

        Every failure — unknown email, wrong password, suspended, awaiting
        activation — produces the SAME generic message, so this form cannot be
        used to discover which accounts exist (FR-AUTH-09).
    --}}
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-input
            label="Email address"
            name="email"
            type="email"
            autocomplete="username"
            required
            autofocus
            :value="old('email')"
        />

        <x-input
            label="Password"
            name="password"
            type="password"
            autocomplete="current-password"
            required
        />

        <div class="flex items-center justify-between">
            <x-checkbox name="remember" label="Remember me" :checked="old('remember')" />

            <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">
                Forgot password?
            </a>
        </div>

        <x-button type="submit" class="w-full">Sign in</x-button>
    </form>
@endsection

@section('footer')
    Don't have an account?
    <a href="{{ route('register') }}" class="font-medium text-brand-600 hover:text-brand-700">Create one</a>
@endsection
