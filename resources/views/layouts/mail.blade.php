{{--
    MAIL LAYOUT — shared by every custom transactional Mailable (Phase 11).

    FR-MAIL-08 / rule S-1 / FR-SYS-06:
    Organisation identity (name, logo, support address, footer) comes from
    BrandingService reading the `settings` table — never hardcoded here, and
    never read from config() at the call site. This is the seam that makes
    per-organisation branding a configuration change in V2 rather than a
    template rewrite (architecture.md §24.2).

    TWO LAYOUTS, ONE SOURCE OF IDENTITY — worth understanding before adding an
    email. Laravel renders the two families of email through different views:

      * Notifications (MailMessage)  -> vendor/mail/html/message.blade.php
      * Custom Mailables (this file) -> layouts/mail.blade.php

    Both are overridden to read the SAME BrandingService, so the two families
    are visually and legally consistent. Neither may reintroduce config('app.name').

    Inline styles are used deliberately: email clients do not reliably support
    external stylesheets or <style> blocks.

    Usage from a Mailable's view:
        <x-mail-layout :subject="$subject"> ... </x-mail-layout>
    or via @extends('layouts.mail') with @section('content').
--}}
@php
    /** @var \App\Services\Settings\BrandingService $branding */
    $branding = app(\App\Services\Settings\BrandingService::class);
    $organisation = $branding->organisationName();
    $logoUrl = $branding->logoUrl();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? $organisation }}</title>
</head>
<body style="margin:0; padding:0; background-color:#faf9f6; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1a1815;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#faf9f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="padding:24px 32px; border-bottom:1px solid #e5e3dc;">
                            {{--
                                A configured logo is used when present, and the
                                organisation name when it is not. logoUrl() returns
                                null rather than a placeholder deliberately: a broken
                                image in a transactional email reads as phishing.
                            --}}
                            @if ($logoUrl !== null)
                                <img src="{{ $logoUrl }}" alt="{{ $organisation }}" height="32" style="height:32px; display:block; border:0;">
                            @else
                                <span style="font-size:18px; font-weight:600;">{{ $organisation }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px; font-size:15px; line-height:1.6;">
                            {{ $slot ?? '' }}
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px; border-top:1px solid #e5e3dc; font-size:12px; color:#8a867b;">
                            {{ $branding->emailFooter() }}
                            <br>
                            Need help? Contact us at
                            <a href="mailto:{{ $branding->supportEmail() }}" style="color:#8a867b;">{{ $branding->supportEmail() }}</a>.
                            <br>
                            This is an automated message — please do not reply to it.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
