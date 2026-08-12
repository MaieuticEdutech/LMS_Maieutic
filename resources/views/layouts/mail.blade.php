{{--
    MAIL LAYOUT — shared by every transactional email (Phase 11).

    FR-MAIL-08 / rule S-1 / FR-SYS-06:
    Organisation identity (name, logo, support address, footer) MUST come from
    BrandingService reading the `settings` table — never hardcoded here, and
    never read from config() at the call site. This is the seam that makes
    per-organisation branding a configuration change in V2 rather than a
    template rewrite (architecture.md §24.2).

    Phase 11 replaces config('app.name') below with the BrandingService values
    once `settings` and that service exist (built in Phase 2).

    Inline styles are used deliberately: email clients do not reliably support
    external stylesheets or <style> blocks.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="padding:24px 32px; border-bottom:1px solid #e4e4e7;">
                            <span style="font-size:18px; font-weight:600;">{{ config('app.name') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px; font-size:15px; line-height:1.6;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px; border-top:1px solid #e4e4e7; font-size:12px; color:#71717a;">
                            &copy; {{ now()->year }} {{ config('app.name') }}.
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
