<?php

declare(strict_types=1);

use App\Support\Qr\QrSvg;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/*
|--------------------------------------------------------------------------
| QR rendering
|--------------------------------------------------------------------------
|
| The failure mode this file exists for: a QR code that renders as a perfectly
| convincing block of squares and does not scan. Nothing downstream catches it
| — not a view test, not a screenshot, not a reviewer. It is discovered by the
| employer holding the printed certificate.
|
| So the central test is a ROUND TRIP. The path is parsed back into a set of
| dark cells and compared against the encoder's own matrix, cell for cell. That
| pins three things at once that would each produce a plausible-looking wrong
| code: losing modules when runs are merged, transposing x and y, and dropping
| a run that reaches the right-hand edge.
|
*/

/**
 * Every dark cell the rendered path actually paints, in matrix coordinates.
 *
 * @return list<string> "y:x", sorted
 */
function paintedCells(string $svg): array
{
    // Not a formality: an SVG with no path at all would otherwise compare
    // equal to an empty expectation and pass every test in this file.
    if (preg_match('/<path d="([^"]*)"/', $svg, $path) !== 1) {
        throw new RuntimeException('The rendered SVG contains no path — nothing was drawn.');
    }

    preg_match_all('/M(\d+) (\d+)h(\d+)v1h-(\d+)z/', $path[1], $runs, PREG_SET_ORDER);

    $cells = [];

    foreach ($runs as [, $x, $y, $width, $closes]) {
        // A run that does not return to where it started leaves an open
        // subpath, which fills as a wedge rather than a row of modules.
        expect($closes)->toBe($width);

        for ($i = 0; $i < (int) $width; $i++) {
            $cells[] = ((int) $y - QrSvg::QUIET_ZONE).':'.((int) $x + $i - QrSvg::QUIET_ZONE);
        }
    }

    sort($cells);

    return $cells;
}

/**
 * The same set, straight from the encoder.
 *
 * @return list<string> "y:x", sorted
 */
function encodedCells(string $data): array
{
    $matrix = Encoder::encode($data, ErrorCorrectionLevel::Q())->getMatrix();
    $cells = [];

    for ($y = 0; $y < $matrix->getHeight(); $y++) {
        for ($x = 0; $x < $matrix->getWidth(); $x++) {
            if ($matrix->get($x, $y) === 1) {
                $cells[] = "{$y}:{$x}";
            }
        }
    }

    sort($cells);

    return $cells;
}

/*
| ═══════════════ THE CODE IS THE CODE ═══════════════
*/
it('paints exactly the modules the encoder produced', function (string $data): void {
    /*
     * This also covers the right-hand edge without needing a contrived
     * payload: the top-right finder pattern puts dark modules in the very last
     * column of rows 0 to 6 of EVERY QR code, so a renderer that only closed a
     * run when it met a light module would drop them here.
     */
    expect(paintedCells((new QrSvg)->render($data, 139)))->toBe(encodedCells($data));
})->with([
    'a verification URL' => 'https://lms.example.test/verify/MAI-CERT-A2C4-9KFP',
    'a local URL' => 'http://localhost:8000/verify/MAI-CERT-TVWX-2367',
    'a long host' => 'https://certificates.maieuticedutech.example.test/verify/MAI-CERT-HJKL-4679',
    'a short string' => 'MAI-CERT-A2C4-9KFP',
]);

it('merges horizontal runs rather than emitting one node per module', function (): void {
    $data = 'https://lms.example.test/verify/MAI-CERT-A2C4-9KFP';

    $svg = (new QrSvg)->render($data, 139);
    $runs = substr_count($svg, 'M');
    $modules = count(encodedCells($data));

    /*
     * Correctness is the round trip above; this only pins that the merge
     * happens at all. Deliberately not a ratio: QR codes are high-entropy by
     * construction, so most runs are one or two modules long and any specific
     * saving figure would be a number invented to match one payload.
     */
    expect($runs)->toBeLessThan($modules);
});

/*
| ═══════════════ THE QUIET ZONE ═══════════════
*/
it('surrounds the code with a quiet zone on all four sides', function (): void {
    $data = 'https://lms.example.test/verify/MAI-CERT-A2C4-9KFP';

    $svg = (new QrSvg)->render($data, 139);
    $modules = Encoder::encode($data, ErrorCorrectionLevel::Q())->getMatrix()->getWidth();
    $span = $modules + (QrSvg::QUIET_ZONE * 2);

    // The viewBox, not the pixel size, is what carries the border — the code
    // has to keep it at every rendered size.
    expect($svg)->toContain("viewBox=\"0 0 {$span} {$span}\"");

    // Nothing painted inside the border on any edge.
    foreach (paintedCells($svg) as $cell) {
        [$y, $x] = array_map(intval(...), explode(':', $cell));

        expect($x)->toBeGreaterThanOrEqual(0)
            ->and($y)->toBeGreaterThanOrEqual(0)
            ->and($x)->toBeLessThan($modules)
            ->and($y)->toBeLessThan($modules);
    }
});

it('renders square at the requested size', function (): void {
    // A stretched QR code does not scan. The certificate's own frame for it is
    // slightly wider than tall, so this is a real risk and not a hypothetical.
    $svg = (new QrSvg)->render('https://lms.example.test/verify/MAI-CERT-A2C4-9KFP', 220);

    expect($svg)->toContain('width="220"')
        ->and($svg)->toContain('height="220"');
});

/*
| ═══════════════ NOTHING BUT GEOMETRY GOES IN ═══════════════
*/
it('never reproduces the encoded data as markup', function (): void {
    /*
     * The payload is turned into a module grid and nothing else — it is not
     * echoed into a title, a label or a comment. That is what makes the SVG
     * safe to embed as a data URI without escaping anything: there is no path
     * by which a URL could carry markup into the document.
     */
    $svg = (new QrSvg)->render('https://lms.example.test/v/"><script>alert(1)</script>', 139);

    expect($svg)->not->toContain('script')
        ->and($svg)->not->toContain('lms.example.test')
        ->and($svg)->toStartWith('<svg ')
        ->and($svg)->toEndWith('</svg>');
});

it('keeps dark modules on a light field', function (): void {
    // Fixed, not configurable: teal-on-cream would sit in the certificate's
    // palette beautifully and fail to scan.
    $svg = (new QrSvg)->render('https://lms.example.test/v/1', 139);

    expect($svg)->toContain('fill="#ffffff"')
        ->and($svg)->toContain('fill="#1a1815"');
});
