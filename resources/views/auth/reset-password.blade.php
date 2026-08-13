@extends('layouts.auth')

@section('title', 'Choose a new password')
@section('heading', 'Choose a new password')
@section('subheading', 'Pick something you have not used here before.')

@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf

        {{-- The token identifies the reset request. It is compared against a
             HASHED stored value, expires, and is deleted on use (ADR-004). --}}
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-input
            label="Email address"
            name="email"
            type="email"
            autocomplete="username"
            required
            :value="old('email', $request->email)"
        />

        <x-input label="New password" name="password" type="password" autocomplete="new-password" required autofocus />

        <x-input
            label="Confirm new password"
            name="password_confirmation"
            type="password"
            autocomplete="new-password"
            required
        />

        <x-alert variant="info">
            Setting a new password signs you out everywhere else.
        </x-alert>

        <x-button type="submit" class="w-full">Reset password</x-button>
    </form>
@endsection
