@extends('layouts.auth')

@section('title', 'Reset password')
@section('heading', 'Reset your password')
@section('subheading', "Enter your email address and we'll send you a link to choose a new password.")

@section('content')
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
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

        <x-button type="submit" class="w-full">Email password reset link</x-button>
    </form>
@endsection

@section('footer')
    <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">Back to sign in</a>
@endsection
