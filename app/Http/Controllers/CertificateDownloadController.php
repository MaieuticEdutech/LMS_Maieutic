<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\Certificate\CertificateDocument;
use Symfony\Component\HttpFoundation\Response;

/**
 * The certificate as a PDF file.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * WHY THIS EXISTS RATHER THAN window.print().
 *
 * The first version of this feature had Download open the document and call
 * the browser's print dialog, on the reasoning that "Save as PDF" is built
 * into every browser and costs no dependency. In practice that hands the
 * student two settings they have to get right — the Destination is whatever
 * they printed to last, and Chrome's "Headers and footers" box overrides
 * `@page { margin: 0 }` and stamps a URL and a page number across the artwork.
 *
 * A credential should not require its holder to configure a print dialog. This
 * returns a file.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * Generated on demand and never stored. A saved PDF would be a second source
 * of truth that could disagree with the row it came from, and would need
 * invalidating every time the branding changed — the same reasoning that kept
 * a pdf_path column out of the certificates table.
 */
final class CertificateDownloadController extends Controller
{
    public function __invoke(Certificate $certificate, CertificateDocument $document): Response
    {
        // Same authorisation as the document it renders. This is the surface
        // that actually hands over the artefact, so it may never be the laxer
        // of the two.
        $this->authorize('view', $certificate);

        return response($document->pdf($certificate), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',

            // `attachment` is the whole point: `inline` would open the PDF in
            // the browser's viewer and leave the student hunting for a save
            // button, which is the problem this route was added to solve.
            'Content-Disposition' => 'attachment; filename="'.$document->filename($certificate).'"',

            // A certificate is not a public document and must not sit in a
            // shared proxy cache keyed only by URL.
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
