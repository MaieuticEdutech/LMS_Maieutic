<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment gateway webhooks
|--------------------------------------------------------------------------
|
| THIS FILE CONTROLS COURSE ACCESS. It is the single most security-sensitive
| surface in the application, and the reason for the project's central rule:
|
|     A browser saying "payment succeeded" grants NOTHING.
|     Only a signature-verified webhook (or its reconciliation equivalent)
|     may result in an enrollment.
|     — Development Rules 21 & 22, ADR-006, FR-PAY-05, AC-09
|
| Registered WITHOUT the `web` middleware group: no session, no cookies and
| no CSRF token, because a payment gateway cannot present one. Authenticity
| comes from HMAC-SHA256 signature verification over the RAW request body,
| using a constant-time comparison, performed BEFORE the payload is parsed
| (NFR-SEC-13, FR-PAY-06).
|
| REQUIRED PROPERTIES OF THE HANDLER (architecture.md §11.3):
|   1. Verify signature against the raw body first. Reject with 400 and log a
|      security event on failure — creating nothing.
|   2. Persist the event keyed on the gateway event id (UNIQUE) for
|      idempotency, then acknowledge 2xx FAST.
|   3. Defer all business processing to a queued job. A duplicate delivery
|      must produce exactly one payment, one enrollment and one email (AC-11).
|   4. Re-verify amount and currency against the order before granting
|      anything; a mismatch blocks enrollment and raises an alert (FR-PAY-13).
|   5. Call GrantEnrollment — the ONLY code path that may create an
|      enrollment. Never insert one here.
|   6. Rate limited via the `webhook` limiter, though the signature is the
|      real control.
|
| Delivered by Phase 12.
|
*/

Route::name('razorpay')
    ->group(static function (): void {
        // Phase 12: Route::post('/razorpay', ProcessRazorpayWebhookController::class);
    });
