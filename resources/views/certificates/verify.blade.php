@extends('layouts.public')

@section('title', 'Certificate '.$certificate->number)

@section('content')
    {{--
        Public certificate verification.

        Reached by anyone holding the number — see routes/web.php for why that
        is safe and CertificatePolicy for what is deliberately not shown.

        EVERY FIELD HERE IS A SNAPSHOT COLUMN. Nothing reads through to `users`
        or `courses` for the text of the award. A learner editing their profile,
        or an administrator renaming a course, must not alter a document already
        verified by a third party.

        Deliberately absent: the holder's email, their other certificates, their
        progress, and any link into the product's authenticated surface. A
        stranger confirming a credential needs the claim and nothing else.
    --}}
    @php($branding = app(\App\Services\Settings\BrandingService::class))

    <div class="mx-auto w-full max-w-[760px] px-5 pb-24 pt-12 lg:px-10">

        <div class="mb-8 flex items-center gap-2.5">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25"
                 stroke-linecap="round" stroke-linejoin="round" class="text-honeydew" aria-hidden="true">
                <path d="M20 6 9 17l-5-5"></path>
            </svg>
            <p class="eyebrow text-honeydew">Verified certificate</p>
        </div>

        <div class="overflow-hidden rounded-card border border-neutral-200 bg-white">
            <div class="p-10 text-white"
                 style="background:linear-gradient(118deg,#052826 0%,#052826 78%,#800d07 78%)">

                <img src="{{ asset('images/logo-maieutic-reversed.png') }}"
                     alt="{{ $branding->organisationName() }}"
                     class="mb-6 h-7 w-auto opacity-95">

                <div class="mb-3 font-mono text-[10px] font-semibold tracking-[0.16em] text-teal-300">
                    CERTIFICATE OF COMPLETION
                </div>

                <p class="text-sm text-white/70">This certifies that</p>

                <p class="mt-1.5 font-serif text-[34px]/[1.15] font-medium">{{ $certificate->recipient_name }}</p>

                <p class="mt-4 text-sm text-white/70">has successfully completed</p>

                <p class="mt-1.5 font-serif text-[26px]/[1.2] font-medium">{{ $certificate->course_title }}</p>
            </div>

            <dl class="grid gap-6 px-10 py-7 sm:grid-cols-3">
                <div>
                    <dt class="text-[13px] text-neutral-500">Awarded</dt>
                    <dd class="mt-1 font-medium text-neutral-900">{{ $certificate->issued_at->format('j F Y') }}</dd>
                </div>

                <div>
                    <dt class="text-[13px] text-neutral-500">Certificate ID</dt>
                    <dd class="mt-1 font-mono text-sm font-medium text-neutral-900">{{ $certificate->number }}</dd>
                </div>

                <div>
                    <dt class="text-[13px] text-neutral-500">Issued by</dt>
                    <dd class="mt-1 font-medium text-neutral-900">{{ $branding->organisationName() }}</dd>
                </div>
            </dl>
        </div>

        <p class="mt-6 text-sm text-neutral-500">
            This record was confirmed against {{ $branding->organisationName() }}'s register on
            {{ now()->format('j F Y') }}. A certificate ID that does not resolve to a page like this one
            has not been issued by {{ $branding->organisationName() }}.
        </p>

        {{-- "Download" links here with ?print=1. Print-to-PDF is built into
             every browser and produces the same document as a server-rendered
             PDF would, without adding a Composer dependency to another track's
             file. The @media print rules in app.css drop the site chrome. --}}
        @if (request()->boolean('print'))
            <script>
                window.addEventListener('load', () => window.print());
            </script>
        @endif
    </div>
@endsection
