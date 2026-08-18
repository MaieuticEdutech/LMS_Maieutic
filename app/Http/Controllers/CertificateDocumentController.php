<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\Certificate\CertificateDocument;
use Illuminate\Contracts\View\View;

/**
 * The certificate itself — the printable document, not the verification receipt.
 *
 * TWO SURFACES, TWO AUDIENCES, AND THEY ARE NOT THE SAME PAGE:
 *
 *   /verify/{number}  is public, unauthenticated, and answers a stranger's
 *       question — "is this claim real?". It shows the assertion and nothing
 *       else (VerifyCertificateController).
 *
 *   /certificates/{number}  is this: the holder's own copy, laid out as the
 *       physical certificate, for keeping, printing and downloading. It is
 *       authorised per record by CertificatePolicy — the holder, or a super
 *       admin.
 *
 * The split matters because the document carries the QR code, and the QR code
 * points at the public page. Serving the document publicly would make the code
 * a loop back to itself and hand anyone holding a number a print-ready
 * certificate in someone else's name.
 *
 * A plain controller rather than a Livewire component: the page has no state
 * and no interaction, and it must render standalone.
 */
final class CertificateDocumentController extends Controller
{
    public function __invoke(Certificate $certificate, CertificateDocument $document): View
    {
        $this->authorize('view', $certificate);

        return view('certificates.document', $document->data($certificate, pdf: false));
    }
}
