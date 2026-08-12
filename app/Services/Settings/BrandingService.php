<?php

declare(strict_types=1);

namespace App\Services\Settings;

/**
 * Resolves organisation identity for layouts and emails (FR-MAIL-08, FR-SYS-06).
 *
 * THIS IS A MULTI-TENANCY SEAM (planning.md rule S-1).
 *
 * Every place the application says who it is — page headers, email layouts,
 * sender addresses, footers — asks this service. Nothing hardcodes the
 * organisation's name, and nothing reads it from config() at the call site.
 *
 * In V2 this class resolves per-organisation from the tenant context, and
 * every existing view and mailable keeps working without modification. That
 * is the difference between "add an organisation column" and "rewrite the
 * application", which is the outcome the customer asked us to guarantee.
 *
 * Settings keys are namespaced `branding.*` so the Phase 4 admin screen can
 * present them as one group.
 */
final class BrandingService
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Organisation display name.
     *
     * Falls back to config('app.name') ONLY so that a fresh install before
     * seeding still renders. Once seeded, the database value wins — and it is
     * the database value that becomes per-organisation in V2.
     */
    public function organisationName(): string
    {
        $name = $this->settings->string('branding.organisation_name');

        return $name !== '' ? $name : config()->string('app.name');
    }

    public function supportEmail(): string
    {
        $email = $this->settings->string('branding.support_email');

        return $email !== '' ? $email : config()->string('mail.from.address');
    }

    /**
     * Absolute URL of the organisation logo, or null if none is configured.
     *
     * Null is a legitimate answer — layouts fall back to the organisation
     * name as text rather than rendering a broken image.
     */
    public function logoUrl(): ?string
    {
        $path = $this->settings->string('branding.logo_path');

        if ($path === '') {
            return null;
        }

        return str_starts_with($path, 'http')
            ? $path
            : rtrim(config()->string('app.url'), '/').'/storage/'.ltrim($path, '/');
    }

    /**
     * Sender identity for outgoing mail.
     *
     * @return array{address: string, name: string}
     */
    public function mailFrom(): array
    {
        $address = $this->settings->string('branding.mail_from_address');
        $name = $this->settings->string('branding.mail_from_name');

        return [
            'address' => $address !== '' ? $address : config()->string('mail.from.address'),
            'name' => $name !== '' ? $name : $this->organisationName(),
        ];
    }

    /**
     * Footer line shown at the bottom of every transactional email.
     */
    public function emailFooter(): string
    {
        $footer = $this->settings->string('branding.email_footer');

        return $footer !== ''
            ? $footer
            : '© '.date('Y').' '.$this->organisationName().'.';
    }
}
