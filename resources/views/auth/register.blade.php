@extends('layouts.auth')

@section('title', 'Create account')
@section('heading', 'Create your account')
@section('subheading', 'Registering does not grant access to any course. Courses are purchased separately.')

@section('content')
    {{--
        Student self-registration only.

        There is deliberately NO role selector. The role is forced to `student`
        inside RegisterStudent and is never read from request input, so adding
        a "role" field to this form by hand achieves nothing
        (FR-RBAC-07, NFR-SEC-07).

        The subheading states the access rule up front: a student account is
        not course access. Access comes from an enrollment, which comes from a
        verified payment (FR-ENR-01, ADR-006).
    --}}
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <x-input label="Full name" name="name" autocomplete="name" required autofocus :value="old('name')" />

        <x-input
            label="Email address"
            name="email"
            type="email"
            autocomplete="username"
            required
            :value="old('email')"
            hint="We'll send a verification link here. Your account stays inactive until you confirm it."
        />

        <x-input label="Password" name="password" type="password" autocomplete="new-password" required />

        <x-input
            label="Confirm password"
            name="password_confirmation"
            type="password"
            autocomplete="new-password"
            required
        />

        <x-button type="submit" class="w-full">Create account</x-button>
    </form>
@endsection

@section('footer')
    Already have an account?
    <a href="{{ route('login') }}" class="font-medium text-teal-600 underline-offset-[0.18em] transition-colors hover:text-teal-700 hover:underline">Sign in</a>
@endsection
