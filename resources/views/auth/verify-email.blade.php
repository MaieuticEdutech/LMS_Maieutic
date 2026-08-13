@extends('layouts.auth')

@section('title', 'Verify your email')
@section('heading', 'Confirm your email address')
@section('subheading', 'One step left before you can sign in.')

@section('content')
    {{--
        Shown to a self-registered user whose account is still
        `pending_verification`. Until the link is clicked, the account cannot
        authenticate — only `active` passes the check inside
        Fortify::authenticateUsing() (FR-AUTH-11).
    --}}
    @if (session('status') === 'verification-link-sent')
        <x-alert variant="success" class="mb-5">
            A new verification link has been sent to your email address.
        </x-alert>
    @endif

    <p class="text-sm text-neutral-600">
        We've sent a verification link to your email address. Please click it to activate your
        account. If it hasn't arrived, check your spam folder or request a new one below.
    </p>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button type="submit">Resend verification email</x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button type="submit" variant="ghost">Sign out</x-button>
        </form>
    </div>
@endsection
