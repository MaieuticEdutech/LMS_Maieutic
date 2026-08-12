@extends('layouts.auth')

@section('title', 'Confirm password')
@section('heading', 'Confirm your password')
@section('subheading', 'Please confirm your password before continuing.')

@section('content')
    {{--
        Re-authentication gate for sensitive actions. Guards against a walked-up
        or hijacked session performing a high-impact change without proving the
        password is known.
    --}}
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <x-input label="Password" name="password" type="password" autocomplete="current-password" required autofocus />

        <x-button type="submit" class="w-full">Confirm</x-button>
    </form>
@endsection
