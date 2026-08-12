@extends('layouts.auth')

@section('title', 'Set your password')
@section('heading', 'Welcome — set your password')
@section('subheading', 'Choose a password to finish setting up your account.')

@section('content')
    {{--
        First-time activation for an account created on the user's behalf —
        in Phase 12, by a verified purchase (FR-MAIL-01, FR-MAIL-04).

        This is NOT a password reset: the account has never had a password.
        `users.password` is NULL until this form is submitted, which is why
        such an account cannot be logged into by any means beforehand.

        No password was ever emailed to get here — only a one-time link that is
        hashed at rest, expires, and dies on use (FR-MAIL-02, AC-14).
    --}}
    <form method="POST" action="{{ route('activate.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <x-input
            label="Email address"
            name="email"
            type="email"
            autocomplete="username"
            required
            :value="old('email', $email)"
            hint="This is the address your account was created with."
        />

        <x-input label="Choose a password" name="password" type="password" autocomplete="new-password" required autofocus />

        <x-input
            label="Confirm password"
            name="password_confirmation"
            type="password"
            autocomplete="new-password"
            required
        />

        <x-button type="submit" class="w-full">Activate my account</x-button>
    </form>

    <form method="POST" action="{{ route('activate.resend') }}" class="mt-5 border-t border-zinc-200 pt-5">
        @csrf
        <input type="hidden" name="email" value="{{ old('email', $email) }}">
        <p class="text-sm text-zinc-500">
            Link expired?
            <button type="submit" class="font-medium text-brand-600 hover:text-brand-700">
                Send me a new one
            </button>
        </p>
    </form>
@endsection
