<?php

declare(strict_types=1);

namespace App\Support\Qr;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\ByteMatrix;
use BaconQrCode\Encoder\Encoder;

/**
 * Renders a QR code as an inline SVG element.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * NO NEW COMPOSER DEPENDENCY.
 *
 * bacon/bacon-qr-code is already locked — laravel/fortify requires it for the
 * two-factor setup code, so it is installed on every machine that can run this
 * application at all. composer.json belongs to Track C and a package there needs
 * a recorded Rule 6 justification; nothing needed one here.
 *
 * Only the ENCODER is used, not bacon's renderers. Its SVG back end emits a
 * whole standalone document with an XML prolog and its own margin handling,
 * which is the wrong shape for something that has to sit inside a page at an
 * exact size. Reading the module matrix and writing the path directly is a
 * dozen lines and gives the certificate exactly the geometry it asks for.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class QrSvg
{
    /**
     * Blank modules around the code, REQUIRED BY THE SPEC AND BY REALITY.
     *
     * A reader locates a QR code by finding a quiet border around it. Without
     * this the code still looks like a QR code to a person and fails to scan
     * for a phone — which is the worst possible failure for something printed
     * on a certificate, because it is only discovered by the employer.
     *
     * Four modules is the specified minimum.
     */
    public const QUIET_ZONE = 4;

    /**
     * The colours are FIXED, not arguments.
     *
     * A scanner needs dark modules on a light field. Exposing these as
     * parameters would let a future caller ask for the certificate's ink
     * colours — teal on cream reads beautifully and scans badly — and the
     * result would look completely correct on screen. The design's dark ink is
     * used so it still sits in the palette.
     */
    private const DARK = '#1a1815';

    private const LIGHT = '#ffffff';

    /**
     * @param  string  $data  what a scanner receives — for a certificate, its public verification URL
     * @param  int  $size  rendered edge length in CSS pixels, quiet zone included
     *
     * Carries no accessible name of its own: this is embedded as the src of an
     * <img>, which is what dompdf understands — it skips inline <svg> silently,
     * producing a page with no code on it and no error anywhere. The alt text
     * belongs on that element.
     */
    public function render(string $data, int $size): string
    {
        /*
         * Error correction Q (~25%) rather than the usual M (~15%).
         *
         * This code gets printed, folded, photocopied and photographed off a
         * desk at an angle. The extra redundancy costs a slightly denser grid
         * and nothing else: at the URL lengths involved both levels land on
         * the same symbol version anyway, so Q is free here.
         */
        $matrix = Encoder::encode($data, ErrorCorrectionLevel::Q())->getMatrix();

        $span = $matrix->getWidth() + (self::QUIET_ZONE * 2);

        /*
         * THE viewBox IS MEASURED IN PIXELS, NOT IN MODULES, AND THAT IS LOAD
         * BEARING.
         *
         * dompdf's SVG renderer draws one viewBox unit as one CSS pixel and
         * ignores both the width/height attributes and the CSS box it sits in.
         * A viewBox of "0 0 45 45" therefore came out as a 45px code inside a
         * 190px hole — correct, scannable in principle, and far too small to
         * scan in practice. It looked right in every browser, because browsers
         * honour the attributes.
         *
         * So the module grid is scaled into pixel coordinates here rather than
         * left for the renderer to scale. Then one unit IS one pixel and every
         * renderer agrees.
         */
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d" '
                .'shape-rendering="crispEdges">'
                .'<rect width="%1$d" height="%1$d" fill="%2$s"/>'
                .'<path d="%3$s" fill="%4$s"/>'
                .'</svg>',
            $size,
            self::LIGHT,
            $this->path($matrix, $size / $span),
            self::DARK,
        );
    }

    /**
     * One path for the whole code, with horizontal runs merged into single
     * segments.
     *
     * A rect per dark module would be several hundred nodes for one small
     * graphic, repeated on every render. Merging runs typically cuts that by
     * three quarters and produces identical output — the modules in a run are
     * contiguous by definition.
     */
    private function path(ByteMatrix $matrix, float $unit): string
    {
        $width = $matrix->getWidth();
        $segments = [];

        // Where the code starts once the quiet zone is allowed for, in pixels.
        $origin = self::QUIET_ZONE * $unit;

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            $runStart = null;

            /*
             * Deliberately runs one column PAST the edge. A run touching the
             * right-hand edge has no light module after it to close on, so
             * without the extra step the last run of such a row is built and
             * then silently dropped — which corrupts the code in a way that
             * still renders as a plausible-looking QR.
             */
            for ($x = 0; $x <= $width; $x++) {
                $isDark = $x < $width && $matrix->get($x, $y) === 1;

                if ($isDark && $runStart === null) {
                    $runStart = $x;

                    continue;
                }

                if (! $isDark && $runStart !== null) {
                    $length = ($x - $runStart) * $unit;

                    $segments[] = sprintf(
                        'M%s %sh%sv%sh-%sz',
                        $this->coord($origin + ($runStart * $unit)),
                        $this->coord($origin + ($y * $unit)),
                        $this->coord($length),
                        $this->coord($unit),
                        $this->coord($length),
                    );

                    $runStart = null;
                }
            }
        }

        return implode('', $segments);
    }

    /**
     * A coordinate, at the shortest length that still lands within a thousandth
     * of a pixel — trailing zeroes on several hundred path segments are pure
     * document weight.
     */
    private function coord(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
