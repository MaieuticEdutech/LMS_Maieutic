<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Contracts\View\View;

/**
 * Public certificate verification (design handoff §7 — "verifiable by its ID").
 *
 * A plain controller rather than a Livewire component: this page has no state,
 * no interaction and is the one student-facing surface most likely to be opened
 * by someone who has never seen this product. It should be a fast, cacheable
 * document.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * NO AUTHENTICATION, NO POLICY, ON PURPOSE.
 *
 * The whole value of a certificate is that a third party can confirm it. See
 * routes/web.php and CertificatePolicy for why possession of the number is the
 * authorisation, and what is deliberately not shown here.
 *
 * The route model binding resolves on `number` (Certificate::getRouteKeyName),
 * so an unknown or mistyped number 404s — the same response as a number that
 * never existed. There is no "that certificate was revoked" state to leak,
 * because revocation does not exist yet, and when it does it will be a
 * deliberate message rather than a different status code.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class VerifyCertificateController extends Controller
{
    public function __invoke(Certificate $certificate): View
    {
        /*
         * The course is loaded to LINK to it, never for the title printed on
         * the page — that is the snapshot taken at issue. A course renamed
         * afterwards must not silently rewrite a document already verified.
         */
        $certificate->loadMissing('course');

        return view('certificates.verify', ['certificate' => $certificate]);
    }
}
