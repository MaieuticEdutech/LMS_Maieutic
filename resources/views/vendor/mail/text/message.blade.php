{{--
    Plain-text counterpart of the branded message wrapper (FR-MAIL-08).

    Every email is sent multipart; this is the part shown by clients that do
    not render HTML, and by most spam filters when they score the message. It
    must carry the SAME organisation identity as the HTML version — a mismatch
    between the two parts is itself a spam signal.

    See the HTML version for the full rationale on why this file is overridden.
--}}
@php
    /** @var \App\Services\Settings\BrandingService $branding */
    $branding = app(\App\Services\Settings\BrandingService::class);
    $organisation = $branding->organisationName();
@endphp
<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ $organisation }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            {{ $branding->emailFooter() }}

            @lang('Need help? Contact us at') {{ $branding->supportEmail() }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
