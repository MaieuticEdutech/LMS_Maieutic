<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\Settings\BrandingService;
use App\Support\Qr\QrSvg;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
 *       physical certificate, for keeping and printing. It is authorised per
 *       record by CertificatePolicy — the holder, or a super admin.
 *
 * The split matters because the document carries the QR code, and the QR code
 * points at the public page. Serving the document publicly would make the code
 * a loop back to itself and hand anyone holding a number a print-ready
 * certificate in someone else's name.
 *
 * A plain controller rather than a Livewire component: the page has no state
 * and no interaction, and it must render standalone so that printing it
 * produces the certificate and nothing else.
 */
final class CertificateDocumentController extends Controller
{
    /**
     * Edge length of the QR block in the design, in sheet pixels.
     *
     * The design's own frame is 152.5 x 139.1 — very slightly wider than tall.
     * A QR code rendered to those bounds would be stretched, and a stretched
     * code is a code that does not scan, so it is drawn SQUARE at the smaller
     * dimension. This is the one place the document deliberately departs from
     * the source design, because the alternative is a graphic that looks
     * correct and fails at the only job it has.
     */
    private const QR_SIZE = 139;

    public function __invoke(
        Request $request,
        Certificate $certificate,
        QrSvg $qr,
        BrandingService $branding,
    ): View {
        $this->authorize('view', $certificate);

        $verifyUrl = route('certificates.verify', $certificate);

        return view('certificates.document', [
            'certificate' => $certificate,
            'branding' => $branding,

            /*
             * The QR encodes the PUBLIC verification URL, never this page.
             * Whoever scans it off a printed sheet is the person with the
             * least reason to have an account here.
             */
            'qr' => $qr->render(
                $verifyUrl,
                self::QR_SIZE,
                "Scan to verify certificate {$certificate->number}",
            ),

            // Printed under the code for anyone typing it by hand. The scheme
            // is dropped because it is noise on paper, not because it is
            // optional — the QR carries the real, absolute URL.
            'verifyLabel' => (string) preg_replace('#^https?://#', '', $verifyUrl),

            /*
             * Opening the page with ?print=1 fires the browser's print dialog
             * on load. That is what the Download button uses — see the note in
             * the view for why this rather than a server-generated PDF.
             */
            'autoPrint' => $request->boolean('print'),
        ]);
    }
}
