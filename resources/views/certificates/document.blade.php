{{--
    The certificate, as a document. Renders to the screen and to a PDF file
    from this one template — see App\Services\Certificate\CertificateDocument.

    ═════════════════════════════════════════════════════════════════════════
    THIS IS A TRANSCRIPTION OF certificate/LMS_certificate_design.pptx, NOT AN
    INTERPRETATION OF IT.

    Every coordinate below was read out of the deck's slide XML and converted
    once: EMU / 9525 = CSS pixels, and points x 4/3 = CSS pixels. The slide is
    13335000 x 9429750 EMU, which is exactly 1400 x 990 px — and exactly the
    A-series ratio, which is why it drops onto A4 landscape with no letterboxing.

    So the numbers are odd on purpose. 819.2 is where the deck puts that text.
    Rounding them to tidy values, or to Tailwind's spacing steps, is how a
    faithful layout turns into an approximate one.
    ═════════════════════════════════════════════════════════════════════════

    WHY THIS PAGE OWNS ITS CSS INSTEAD OF USING TAILWIND

    It is a fixed-geometry document, not a responsive screen: sixty absolutely
    positioned elements at sub-pixel offsets, which as arbitrary-value utility
    classes would be unreadable and unreviewable. It also has to render
    standalone, and app.css's `@source` list does not cover this directory
    reliably — a class that exists only here can be dropped from the build
    depending on how warm the Blade cache is. A document that silently loses
    its layout is not an acceptable failure mode for a credential.

    WHAT DOMPDF CANNOT DO, AND WHY THIS FILE LOOKS THE WAY IT DOES

    No CSS custom properties, no clip-path, no transform. So: colours are
    written out as literal hex rather than tokens, and the two brand triangles
    and the divider diamond are inline SVG polygons rather than clipped or
    rotated boxes. All three render identically in a browser, so there is still
    ONE template — which matters more than the tidiness, because a separate
    PDF template drifts from the screen one and nobody notices until a student
    downloads a certificate that does not match what they were shown.

    Everything a reader sees comes from the certificate's SNAPSHOT columns.
    Nothing joins to `users` or `courses` for printed text — a learner renaming
    themselves, or an admin retitling a course, must not rewrite a document an
    employer has already verified.
--}}
@php
    $signatory = $branding->certificateSignatory();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $certificate->number }}</title>

    @unless ($pdf)
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endunless

    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Georgia, 'Times New Roman', 'Liberation Serif', 'Nimbus Roman', serif;
        }

        .sheet > * { position: absolute; }

        /* ───────── frame ───────── */

        .frame-outer { left: 28px; top: 28px; width: 1344px; height: 934px; border: 3px solid #024e4a; }
        .frame-inner { left: 36px; top: 36px; width: 1328px; height: 918px; border: 1px solid #024e4a; }

        /* The eight corner ticks: 80px long, 3px thick, laid over the frame. */
        .tick { background: #800d07; }
        .tick-h { width: 80px; height: 3px; }
        .tick-v { width: 3px; height: 80px; }

        /* The brand mark: two right triangles sharing a right angle at the
           inner frame's top-left corner. Both are rotated 90 degrees in the
           slide; the polygon points in the markup are the result, so nothing
           has to be rotated at render time — which dompdf could not do. */
        .mark-teal { left: 40px; top: 40px; width: 131.3px; height: 115.5px; }
        .mark-red { left: 40px; top: 39.8px; width: 80px; height: 68.3px; }

        /* ───────── masthead ───────── */

        .logo { left: 538.3px; top: 51.5px; width: 312.1px; height: 82.3px; }

        .rule-left { left: 600px; top: 171.5px; width: 80px; height: 1px; background: #00615c; }
        .rule-right { left: 720px; top: 171.5px; width: 80px; height: 1px; background: #00615c; }

        /* An 8px square rotated 45 degrees about its centre (700, 172) spans
           11.31px each way, which is where these bounds come from. */
        .diamond { left: 694.3px; top: 166.3px; width: 11.3px; height: 11.3px; }

        .title {
            left: 0; top: 200px; width: 1400px;
            text-align: center;
            font-size: 56px;
            line-height: 0.96;
            letter-spacing: -0.56px;
            color: #024e4a;
        }

        .eyebrow {
            left: 0; top: 270px; width: 1400px;
            text-align: center;
            font-family: 'Courier New', Courier, 'Liberation Mono', monospace;
            font-size: 10.2px;
            line-height: 1.32;
            letter-spacing: 1.76px;
            color: #800d07;
        }

        /* ───────── the award ───────── */

        .presented-to {
            left: 106px; top: 340px; width: 1188px;
            text-align: center;
            font-size: 22px;
            font-style: italic;
            line-height: 1.43;
            letter-spacing: -0.33px;
            color: #4a473f;
        }

        .recipient {
            left: 106px; top: 397px; width: 1188px;
            text-align: center;
            font-size: 82px;
            line-height: 1;
            letter-spacing: -1.64px;
            color: #1a1815;
            /* A long name shrinks rather than wrapping into the rule below it:
               the deck's box is one line tall and the rule is drawn at a fixed
               y. Nothing else on the sheet moves. */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .recipient-rule { left: 460px; top: 511px; width: 480px; height: 1px; background: #b0aca1; }

        .citation {
            left: 236.5px; top: 540px; width: 927px;
            text-align: center;
            font-size: 22px;
            line-height: 1.58;
            letter-spacing: -0.33px;
            color: #4a473f;
        }

        .citation .course {
            font-size: 26.7px;
            font-weight: bold;
        }

        .citation .course-title { color: #024e4a; }

        .congratulations {
            left: 413.5px; top: 674.5px; width: 613px;
            text-align: center;
            font-size: 34.7px;
            font-style: italic;
            line-height: 1.54;
            color: #800d07;
        }

        /* ───────── verification block ───────── */

        /* No dark backing panel behind the code, unlike the deck's placeholder.
           A reader needs dark modules on a light field with a light border, and
           QrSvg builds the quiet zone into the graphic itself. A code that
           matched the mockup exactly would not scan, which defeats the point of
           putting one on a certificate. */
        .qr { left: 1163.5px; top: 676.3px; width: 139px; height: 139px; line-height: 0; }

        /* 195px, not more: the inner frame's right edge is at 1364 and this
           box starts at 1163.5, so anything wider prints the URL over the
           rule. It wraps instead — the QR carries the real link. */
        .verify-url {
            left: 1163.5px; top: 819.2px; width: 195px;
            font-family: 'Courier New', Courier, 'Liberation Mono', monospace;
            font-size: 10.2px;
            line-height: 1.32;
            color: #4a473f;
            word-break: break-all;
        }

        /* ───────── signature ───────── */

        .signature {
            left: 48.9px; top: 797px; width: 495px;
            text-align: center;
            font-size: 30px;
            font-style: italic;
            line-height: 1.54;
            color: #1a1815;
        }

        .signature-rule { left: 71.4px; top: 848px; width: 450px; height: 1px; background: #b0aca1; }

        .signatory-name {
            left: 48.9px; top: 859.1px; width: 495px;
            text-align: center;
            font-family: 'Courier New', Courier, 'Liberation Mono', monospace;
            font-size: 10.2px;
            line-height: 1.32;
            letter-spacing: 1.76px;
            color: #8a867b;
        }

        .signatory-org {
            left: 47.6px; top: 877.4px; width: 495px;
            text-align: center;
            font-family: 'Courier New', Courier, 'Liberation Mono', monospace;
            font-size: 10.2px;
            line-height: 1.32;
            letter-spacing: 1.76px;
            color: #4a473f;
        }

        /* ───────── dated and numbered ───────── */

        /* The deck anchors these to a box running off the right edge of the
           slide; centred, that puts them at x=1241. Re-expressed as a box that
           fits, with the same centre, so nothing overflows the sheet. */
        .completed-label {
            left: 1041px; top: 849.3px; width: 400px;
            text-align: center;
            font-family: 'Courier New', Courier, 'Liberation Mono', monospace;
            font-size: 10.2px;
            line-height: 1.32;
            letter-spacing: 1.76px;
            color: #8a867b;
        }

        .completed-date {
            left: 1041px; top: 867.8px; width: 400px;
            text-align: center;
            font-family: 'Courier New', Courier, 'Liberation Mono', monospace;
            font-size: 10.2px;
            line-height: 1.32;
            letter-spacing: 1.76px;
            color: #4a473f;
        }

        .number-label {
            left: 71.4px; top: 915.8px;
            font-family: 'Courier New', Courier, 'Liberation Mono', monospace;
            font-size: 10.2px;
            line-height: 1.32;
            letter-spacing: 1.76px;
            color: #8a867b;
            white-space: nowrap;
        }

        .number-value {
            left: 156.6px; top: 915px;
            font-family: 'Courier New', Courier, 'Liberation Mono', monospace;
            font-size: 12px;
            line-height: 1.39;
            color: #2e2c27;
            white-space: nowrap;
        }

        @if ($pdf)
        /* ───────── as a PDF ─────────
           No scaling here: the page is set to A4 landscape and dompdf's DPI is
           tuned so that 1400px lands exactly on its width. See
           CertificateDocument::PDF_DPI. */

        @page { margin: 0; }

        body { width: 1400px; height: 990px; background: #faf9f6; }

        .sheet { position: relative; width: 1400px; height: 990px; }
        @else
        /* ───────── on screen ───────── */

        body { background: #e9e7e2; -webkit-font-smoothing: antialiased; }

        .stage {
            position: relative;
            overflow: hidden;
            margin: 24px auto;
            /* --fit is set inline by the script at the foot of the page: how
               far the sheet has to shrink to fit the window. Never above 1 — a
               certificate blown up past its design size looks like a scan. */
            width: calc(1400px * var(--fit, 1));
            height: calc(990px * var(--fit, 1));
            box-shadow: 0 18px 48px rgba(26, 24, 21, 0.18);
        }

        .sheet {
            position: absolute;
            top: 0;
            left: 0;
            width: 1400px;
            height: 990px;
            transform: scale(var(--fit, 1));
            transform-origin: 0 0;
            background: #faf9f6;
            overflow: hidden;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: center;
            padding: 20px 16px 8px;
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
        }

        .toolbar a, .toolbar button {
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            padding: 9px 16px;
            border-radius: 3px;
            border: 1px solid #b0aca1;
            background: #fff;
            color: #3f3d37;
            cursor: pointer;
            text-decoration: none;
        }

        .toolbar .primary { background: #00615c; border-color: #00615c; color: #fff; }
        .toolbar a:hover, .toolbar button:hover { filter: brightness(0.96); }

        .hint {
            width: 100%;
            max-width: 660px;
            text-align: center;
            font-size: 12.5px;
            line-height: 1.6;
            color: #6b6862;
        }

        /* Printing to PAPER. Downloading is a real PDF file now and does not
           come through here at all. Zero margins asks the browser to drop its
           own header and footer, though a viewer whose "Headers and footers"
           box is ticked overrides that — which is exactly why the download is
           not built on printing. */
        @page { size: A4 landscape; margin: 0; }

        @media print {
            body { background: #fff; }
            .stage { margin: 0; width: 297mm; height: 210mm; box-shadow: none; }
            .sheet { transform: scale(0.8017); }
            .toolbar { display: none; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        @endif
    </style>
</head>
<body>

    @unless ($pdf)
        <div class="toolbar">
            <a class="primary" href="{{ route('certificates.download', $certificate) }}">Download PDF</a>

            <button type="button" onclick="window.print()">Print</button>

            {{-- The list is inside the student-only route group, so it is
                 offered only to someone who can actually open it. A super admin
                 reaching this document through the policy would otherwise be
                 handed a link straight to a 403. --}}
            @if (auth()->user()?->isStudent())
                <a href="{{ route('student.certificates.index') }}">All certificates</a>
            @endif

            <a href="{{ route('certificates.verify', $certificate) }}" target="_blank" rel="noopener">Public verification page</a>

            <p class="hint">
                <strong>Download PDF</strong> saves the certificate as a file, sized to one A4 landscape page.
                <strong>Print</strong> sends it to a printer.
            </p>
        </div>
    @endunless

    {{-- On screen the sheet sits inside a .stage that clips it while it is
         scaled down to fit the window. A PDF page IS the sheet, so it needs no
         wrapper and no scaling. --}}
    @if ($pdf)
        <div class="sheet">
    @else
        <div class="stage">
            <div class="sheet">
    @endif

            <div class="frame-outer"></div>
            <div class="frame-inner"></div>

            <div class="tick tick-h" style="left: 28px;   top: 28px;"></div>
            <div class="tick tick-v" style="left: 28px;   top: 28px;"></div>
            <div class="tick tick-h" style="left: 1292px; top: 28px;"></div>
            <div class="tick tick-v" style="left: 1369px; top: 28px;"></div>
            <div class="tick tick-h" style="left: 28px;   top: 959px;"></div>
            <div class="tick tick-v" style="left: 28px;   top: 882px;"></div>
            <div class="tick tick-h" style="left: 1292px; top: 959px;"></div>
            <div class="tick tick-v" style="left: 1369px; top: 882px;"></div>

            {{-- Images, not inline <svg>. dompdf skips an inline svg element
                 silently — no shape, no error — and only renders SVG through
                 an <img> src. --}}
            <img class="mark-teal" src="{{ $markTeal }}" alt="">
            <img class="mark-red" src="{{ $markRed }}" alt="">

            <img class="logo" src="{{ $logoSrc }}" alt="{{ $branding->organisationName() }}">

            <div class="rule-left"></div>
            <img class="diamond" src="{{ $diamond }}" alt="">
            <div class="rule-right"></div>

            <div class="title">Certificate of Completion</div>

            <div class="eyebrow">{{ Str::upper($branding->organisationName()) }} &middot; OFFICIAL RECORD</div>

            <div class="presented-to">This certificate is proudly presented to</div>

            {{-- Snapshot. See the Certificate model for why this is not $certificate->user->name. --}}
            <div class="recipient">{{ $certificate->recipient_name }}</div>

            <div class="recipient-rule"></div>

            <div class="citation">
                <div>in recognition of the successful completion of</div>

                {{-- Both quote marks take the body colour and only the title is
                     teal. The deck has the OPENING quote grey and the closing
                     one teal, which is a text-selection slip rather than a
                     decision — reproducing it faithfully would just be copying
                     a defect. --}}
                <div class="course">&ldquo;<span class="course-title">{{ $certificate->course_title }}</span>&rdquo;</div>

                <div>On {{ $certificate->issued_at->format('j F Y') }}.</div>
            </div>

            <div class="congratulations">Congratulations! You make us proud!</div>

            <img class="qr" src="{{ $qr }}" alt="Scan to verify certificate {{ $certificate->number }}">

            <div class="verify-url">{{ $verifyLabel }}</div>

            @if ($signatory !== '')
                <div class="signature">{{ $signatory }}</div>
                <div class="signature-rule"></div>
                <div class="signatory-name">{{ Str::upper(rtrim($signatory, '.')) }}</div>
            @endif

            <div class="signatory-org">{{ Str::upper($branding->legalName()) }}</div>

            {{--
                The deck labels this "DATE OF COMPLETION" and then prints
                "ISSUED ..." underneath it — the mockup contradicting itself.
                The label wins, because it is the truthful one: IssueCertificate
                sets issued_at from the enrolment's completed_at, so this value
                IS the completion date, not the moment the row was written.
            --}}
            <div class="completed-label">DATE OF COMPLETION</div>
            <div class="completed-date">{{ Str::upper($certificate->issued_at->format('j M Y')) }}</div>

            <div class="number-label">CERT. NO.</div>
            <div class="number-value">{{ $certificate->number }}</div>

        </div>
    @unless ($pdf)
        </div>

        <script>
            // Fit the sheet to the window without ever enlarging it. Recomputed
            // on resize so rotating a tablet does not leave it clipped.
            (function () {
                var root = document.documentElement;

                function fit() {
                    var available = Math.min(document.body.clientWidth - 48, 1400);
                    root.style.setProperty('--fit', Math.min(1, available / 1400).toFixed(4));
                }

                fit();
                window.addEventListener('resize', fit);
            })();
        </script>
    @endunless

</body>
</html>
