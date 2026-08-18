<?php

declare(strict_types=1);

namespace App\Services\Certificate;

use App\Models\Certificate;
use App\Services\Settings\BrandingService;
use App\Support\Qr\QrSvg;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * Builds the certificate document — the same one, for the screen and for a PDF.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * ONE TEMPLATE, TWO OUTPUTS, ON PURPOSE.
 *
 * A separate PDF template would drift from the screen one, and nobody would
 * notice until a student downloaded a certificate that did not match the one
 * they were shown. So certificates/document.blade.php renders both, and the
 * things dompdf cannot do — CSS custom properties, clip-path, transform — are
 * simply not used anywhere in it.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class CertificateDocument
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
    public const QR_SIZE = 139;

    private const LOGO = 'images/logo-maieutic-wordmark.png';

    /**
     * The brand mark: two right triangles sharing a right angle at the inner
     * frame's top-left corner. The deck rotates both 90 degrees; these points
     * are the result, so nothing has to be rotated at render time.
     */
    private const MARK_TEAL = '<svg xmlns="http://www.w3.org/2000/svg" width="131.3" height="115.5" '
        .'viewBox="0 0 131.3 115.5"><polygon points="0,0 0,115.5 131.3,0" fill="#00615c"/></svg>';

    private const MARK_RED = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="68.3" '
        .'viewBox="0 0 80 68.3"><polygon points="0,0 0,68.3 80,0" fill="#800d07"/></svg>';

    /**
     * An 8px square rotated 45 degrees about its centre spans 11.31px each way.
     */
    private const DIAMOND = '<svg xmlns="http://www.w3.org/2000/svg" width="11.3" height="11.3" '
        .'viewBox="0 0 11.3 11.3"><polygon points="5.65,0 11.3,5.65 5.65,11.3 0,5.65" fill="#800d07"/></svg>';

    /**
     * The DPI that makes 1400 design pixels land exactly on A4 landscape.
     *
     * dompdf converts CSS pixels to PDF points as px * 72 / dpi. At the default
     * 96 that gives 1400px = 1050pt, which is a 14.6 inch page — correct, but
     * not a size anybody's printer tray has heard of.
     *
     * A4 landscape is 841.89 x 595.28pt, and the sheet is already the A-series
     * ratio, so ONE factor fits both axes:
     *
     *     1400 * 72 / 119.75 = 841.75pt   (A4 width  841.89 — fits)
     *      990 * 72 / 119.75 = 595.20pt   (A4 height 595.28 — fits)
     *
     * Deliberately a hair UNDER on both. Landing exactly on the boundary is how
     * a one-page document silently becomes two, with the second page blank.
     *
     * Because this scales the px-to-pt mapping rather than any one property,
     * type, rules and images all shrink together and the layout is untouched.
     */
    private const PDF_DPI = 119.75;

    public function __construct(
        private readonly QrSvg $qr,
        private readonly BrandingService $branding,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Certificate $certificate, bool $pdf): array
    {
        $verifyUrl = route('certificates.verify', $certificate);

        return [
            'certificate' => $certificate,
            'branding' => $this->branding,
            'pdf' => $pdf,

            /*
             * The QR encodes the PUBLIC verification URL, never the document.
             * Whoever scans it off a printed sheet is the person with the least
             * reason to have an account here.
             */
            'qr' => $this->svgDataUri($this->qr->render($verifyUrl, self::QR_SIZE)),

            /*
             * The brand mark and the divider diamond, as images rather than
             * markup. The deck draws them as rotated and clipped boxes, which
             * needs clip-path and transform — neither of which dompdf has. As
             * data-URI SVG they render the same in both outputs and the
             * template keeps one code path.
             */
            'markTeal' => $this->svgDataUri(self::MARK_TEAL),
            'markRed' => $this->svgDataUri(self::MARK_RED),
            'diamond' => $this->svgDataUri(self::DIAMOND),

            // Printed under the code for anyone typing it by hand. The scheme
            // is dropped because it is noise on paper, not because it is
            // optional — the QR carries the real, absolute URL.
            'verifyLabel' => (string) preg_replace('#^https?://#', '', $verifyUrl),

            'logoSrc' => $pdf ? $this->logoDataUri() : asset(self::LOGO),
        ];
    }

    public function html(Certificate $certificate, bool $pdf): string
    {
        return View::make('certificates.document', $this->data($certificate, $pdf))->render();
    }

    /**
     * The certificate as PDF bytes.
     */
    public function pdf(Certificate $certificate): string
    {
        $options = new Options;
        $options->set('dpi', self::PDF_DPI);
        $options->set('defaultMediaType', 'print');
        $options->set('isFontSubsettingEnabled', true);

        /*
         * Remote loading stays OFF. The template is ours, but leaving dompdf
         * able to fetch arbitrary URLs turns any future templating mistake into
         * a request this server makes on someone else's behalf — the shape of
         * an SSRF. The logo is inlined as a data URI instead, which also means
         * the PDF renders identically on a machine that cannot reach the site.
         */
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->loadHtml($this->html($certificate, pdf: true), 'UTF-8');
        $dompdf->render();

        $output = $dompdf->output();

        if ($output === '') {
            throw new RuntimeException(
                "Generating the PDF for certificate {$certificate->number} produced nothing.",
            );
        }

        return $output;
    }

    /**
     * The filename the student ends up with on disk.
     *
     * The certificate number, because that is the thing they will search their
     * downloads folder for — and because "certificate.pdf" collides with every
     * other certificate they have ever been sent.
     */
    public function filename(Certificate $certificate): string
    {
        return $certificate->number.'.pdf';
    }

    /**
     * SVG as an <img> source.
     *
     * base64 rather than a percent-encoded literal because the payload contains
     * '#' (every colour) and '"' — characters a raw data URI would need
     * escaping for, and which break quietly rather than loudly when missed.
     */
    private function svgDataUri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * The wordmark as a data URI, for embedding in the PDF.
     *
     * Read off disk rather than fetched over HTTP: with remote loading disabled
     * dompdf could not retrieve it by URL, and a certificate should not depend
     * on the application being able to reach itself to render its own logo.
     */
    private function logoDataUri(): string
    {
        $path = public_path(self::LOGO);

        $bytes = is_readable($path) ? file_get_contents($path) : false;

        if ($bytes === false) {
            throw new RuntimeException("The certificate wordmark is missing from {$path}.");
        }

        return 'data:image/png;base64,'.base64_encode($bytes);
    }
}
