{{--
    BRANDED MAIL MESSAGE WRAPPER — Phase 11 (FR-MAIL-08, rule S-1).

    This file overrides Laravel's stock `mail::message` component, which
    hardcodes config('app.name') in BOTH its header and its footer. That is
    exactly what FR-MAIL-08 forbids, and because every MailMessage notification
    renders through this component, overriding this one file brands the entire
    transactional email set at once — the auth notifications today, and every
    mailable Phases 12, 8 and 9 add later, with no change to those classes.

    Organisation identity is resolved from BrandingService -> `settings`, never
    from config() and never hardcoded. This is the multi-tenancy seam: in V2 the
    service resolves per-organisation and every email follows automatically
    (architecture.md §24.2).

    The logo is used when one is configured and the organisation NAME is used
    when it is not — BrandingService::logoUrl() returns null deliberately rather
    than pointing at a placeholder image, because a broken image in a
    transactional email reads as a phishing attempt.
--}}
@php
    /** @var \App\Services\Settings\BrandingService $branding */
    $branding = app(\App\Services\Settings\BrandingService::class);
    $organisation = $branding->organisationName();
    $logoUrl = $branding->logoUrl();
@endphp
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
@if ($logoUrl !== null)
<img src="{{ $logoUrl }}" class="logo" alt="{{ $organisation }}">
@else
{{ $organisation }}
@endif
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ $branding->emailFooter() }}

{{ __('Need help? Contact us at') }} [{{ $branding->supportEmail() }}](mailto:{{ $branding->supportEmail() }}).
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
