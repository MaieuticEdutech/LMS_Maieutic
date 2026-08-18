{{--
    The certificate, as a document.

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
    standalone when printed, and app.css's `@source` list does not cover this
    directory reliably — a class that exists only here can be dropped from the
    build depending on how warm the Blade cache is. A document that silently
    loses its layout is not an acceptable failure mode for a credential.

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

    {{-- Chrome and Edge use the document title as the default filename when
         the print destination is "Save as PDF", so this is what the student
         ends up with on disk. --}}
    <title>{{ $certificate->number }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}">

    <style>
        /* ───────── page and stage ───────── */

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --sheet-w: 1400px;
            --sheet-h: 990px;

            /* Set inline by the script at the foot of the page: how far the
               sheet has to shrink to fit the window. Never above 1 — a
               certificate blown up past its design size looks like a scan. */
            --scale: var(--fit, 1);

            --serif: Georgia, 'Times New Roman', 'Liberation Serif', 'Nimbus Roman', serif;
            --mono: 'Courier New', Courier, 'Liberation Mono', monospace;

            --ink: #1a1815;
            --ink-soft: #4a473f;
            --ink-faint: #8a867b;
            --teal-deep: #024e4a;
            --teal: #00615c;
            --red: #800d07;
            --rule: #b0aca1;
            --paper: #faf9f6;
        }

        body {
            background: #e9e7e2;
            font-family: var(--serif);
            -webkit-font-smoothing: antialiased;
        }

        .stage {
            position: relative;
            overflow: hidden;
            margin: 24px auto;
            width: calc(var(--sheet-w) * var(--scale));
            height: calc(var(--sheet-h) * var(--scale));
            box-shadow: 0 18px 48px rgb(26 24 21 / 0.18);
        }

        .sheet {
            position: absolute;
            top: 0;
            left: 0;
            width: var(--sheet-w);
            height: var(--sheet-h);
            transform: scale(var(--scale));
            transform-origin: 0 0;
            background: var(--paper);
            overflow: hidden;
        }

        .sheet > * { position: absolute; }

        /* ───────── frame ───────── */

        .frame-outer { left: 28px; top: 28px; width: 1344px; height: 934px; border: 3px solid var(--teal-deep); }
        .frame-inner { left: 36px; top: 36px; width: 1328px; height: 918px; border: 1px solid var(--teal-deep); }

        /* The eight corner ticks: 80px long, 3px thick, laid over the frame. */
        .tick { background: var(--red); }
        .tick-h { width: 80px; height: 3px; }
        .tick-v { width: 3px; height: 80px; }

        /* The brand mark, redrawn as the deck draws it: two right triangles
           sharing a right angle at the inner frame's top-left corner. Both are
           rotated 90 degrees in the slide; the polygons below are the result,
           so nothing has to be rotated at render time. */
        .mark-teal { left: 40px; top: 40px; width: 131.3px; height: 115.5px; background: var(--teal); clip-path: polygon(0 0, 0 100%, 100% 0); }
        .mark-red { left: 40px; top: 39.8px; width: 80px; height: 68.3px; background: var(--red); clip-path: polygon(0 0, 0 100%, 100% 0); }

        /* ───────── masthead ───────── */

        .logo { left: 538.3px; top: 51.5px; width: 312.1px; height: 82.3px; }

        .rule-left { left: 600px; top: 171.5px; width: 80px; height: 1px; background: var(--teal); }
        .rule-right { left: 720px; top: 171.5px; width: 80px; height: 1px; background: var(--teal); }
        .diamond { left: 696px; top: 168px; width: 8px; height: 8px; background: var(--red); transform: rotate(45deg); }

        .title {
            left: 0; top: 200px; width: 1400px;
            text-align: center;
            font-size: 56px;
            line-height: 0.96;
            letter-spacing: -0.56px;
            color: var(--teal-deep);
        }

        .eyebrow {
            left: 0; top: 270px; width: 1400px;
            text-align: center;
            font-family: var(--mono);
            font-size: 10.2px;
            line-height: 1.32;
            letter-spacing: 1.76px;
            color: var(--red);
        }

        /* ───────── the award ───────── */

        .presented-to {
            left: 106px; top: 340px; width: 1188px;
            text-align: center;
            font-size: 22px;
            font-style: italic;
            line-height: 1.43;
            letter-spacing: -0.33px;
            color: var(--ink-soft);
        }

        .recipient {
            left: 106px; top: 397px; width: 1188px;
            text-align: center;
            font-size: 82px;
            line-height: 1;
            letter-spacing: -1.64px;
            color: var(--ink);
            /* A long name shrinks rather than wrapping into the rule below it:
               the deck's box is one line tall and the rule is drawn at a fixed
               y. Nothing else on the sheet moves. */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .recipient-rule { left: 460px; top: 511px; width: 480px; height: 1px; background: var(--rule); }

        .citation {
            left: 236.5px; top: 540px; width: 927px;
            text-align: center;
            font-size: 22px;
            line-height: 1.58;
            letter-spacing: -0.33px;
            color: var(--ink-soft);
        }

        .citation .course {
            font-size: 26.7px;
            font-weight: bold;
        }

        .citation .course-title { color: var(--teal-deep); }

        .congratulations {
            left: 413.5px; top: 674.5px; width: 613px;
            text-align: center;
            font-size: 34.7px;
            font-style: italic;
            line-height: 1.54;
            color: var(--red);
        }

        /* ───────── verification block ───────── */

        /* No dark backing panel behind the code, unlike the deck's placeholder.
           A reader needs dark modules on a light field with a light border, and
           QrSvg builds the quiet zone into the graphic itself. A code that
           matched the mockup exactly would not scan, which defeats the point of
           putting one on a certificate. */
        .qr { left: 1163.5px; top: 676.3px; width: 139px; height: 139px; line-height: 0; }
        .qr svg { display: block; width: 139px; height: 139px; }

        .verify-url {
            left: 1163.5px; top: 819.2px; width: 200px;
            font-family: var(--mono);
            font-size: 10.2px;
            line-height: 1.32;
            color: var(--ink-soft);
            word-break: break-all;
        }

        /* ───────── signature ───────── */

        .signature {
            left: 48.9px; top: 797px; width: 495px;
            text-align: center;
            font-size: 30px;
            font-style: italic;
            line-height: 1.54;
            color: var(--ink);
        }

        .signature-rule { left: 71.4px; top: 848px; width: 450px; height: 1px; background: var(--rule); }

        .signatory-name {
            left: 48.9px; top: 859.1px; width: 495px;
            text-align: center;
            font-family: var(--mono);
            font-size: 10.2px;
            line-height: 1.32;
            letter-spacing: 1.76px;
            color: var(--ink-faint);
        }

        .signatory-org {
            left: 47.6px; top: 877.4px; width: 495px;
            text-align: center;
            font-family: var(--mono);
            font-size: 10.2px;
            line-height: 1.32;
            letter-spacing: 1.76px;
            color: var(--ink-soft);
        }

        /* ───────── dated and numbered ───────── */

        /* The deck anchors these to a box running off the right edge of the
           slide; centred, that puts them at x=1241. Re-expressed as a box that
           fits, with the same centre, so nothing overflows the sheet. */
        .completed-label {
            left: 1041px; top: 849.3px; width: 400px;
            text-align: center;
            font-family: var(--mono);
            font-size: 10.2px;
            line-height: 1.32;
            letter-spacing: 1.76px;
            color: var(--ink-faint);
        }

        .completed-date {
            left: 1041px; top: 867.8px; width: 400px;
            text-align: center;
            font-family: var(--mono);
            font-size: 10.2px;
            line-height: 1.32;
            letter-spacing: 1.76px;
            color: var(--ink-soft);
        }

        .number-label {
            left: 71.4px; top: 915.8px;
            font-family: var(--mono);
            font-size: 10.2px;
            line-height: 1.32;
            letter-spacing: 1.76px;
            color: var(--ink-faint);
            white-space: nowrap;
        }

        .number-value {
            left: 156.6px; top: 915px;
            font-family: var(--mono);
            font-size: 12px;
            line-height: 1.39;
            color: #2e2c27;
            white-space: nowrap;
        }

        /* ───────── screen-only chrome ───────── */

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
            border: 1px solid var(--rule);
            background: #fff;
            color: #3f3d37;
            cursor: pointer;
            text-decoration: none;
        }

        .toolbar .primary { background: var(--teal); border-color: var(--teal); color: #fff; }
        .toolbar a:hover, .toolbar button:hover { filter: brightness(0.96); }

        .hint {
            width: 100%;
            max-width: 620px;
            text-align: center;
            font-size: 12.5px;
            line-height: 1.6;
            color: #6b6862;
        }

        /* ───────── print ───────── */

        @page {
            size: A4 landscape;
            /* Zero margins is also what suppresses the browser's own header
               and footer — without it every printed certificate carries a URL
               and a page number across the artwork. */
            margin: 0;
        }

        @media print {
            /* 1122.4 / 1400. The sheet is already the A-series ratio, so the
               same factor fits both axes with nothing cropped and — kept a
               hair under exact — no phantom second page. */
            :root { --scale: 0.8017; }

            html, body { width: 297mm; height: 210mm; background: #fff; }

            .stage { margin: 0; width: 297mm; height: 210mm; box-shadow: none; }

            .toolbar { display: none; }

            /* Certificates are ink. Without this the browser drops every fill
               and the sheet prints as bare text on white. */
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body @if ($autoPrint) data-print-on-load @endif>

    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">Download PDF</button>

        {{-- The list is inside the student-only route group, so it is offered
             only to someone who can actually open it. A super admin reaching
             this document through the policy would otherwise be handed a link
             straight to a 403. --}}
        @if (auth()->user()?->isStudent())
            <a href="{{ route('student.certificates.index') }}">All certificates</a>
        @endif
        <a href="{{ route('certificates.verify', $certificate) }}" target="_blank" rel="noopener">Public verification page</a>
        <p class="hint">
            Choose <strong>Save as PDF</strong> as the destination to keep a copy. The certificate is
            already sized to one A4 landscape page.
        </p>
    </div>

    <div class="stage">
        <div class="sheet">

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

            <div class="mark mark-teal"></div>
            <div class="mark mark-red"></div>

            <img class="logo"
                 src="{{ asset('images/logo-maieutic-wordmark.png') }}"
                 alt="{{ $branding->organisationName() }}">

            <div class="rule-left"></div>
            <div class="diamond"></div>
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

            <div class="qr">{!! $qr !!}</div>

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
    </div>

    <script>
        // Fit the sheet to the window without ever enlarging it. Recomputed on
        // resize so rotating a tablet does not leave it clipped.
        (function () {
            var root = document.documentElement;

            function fit() {
                var available = Math.min(document.body.clientWidth - 48, 1400);
                root.style.setProperty('--fit', Math.min(1, available / 1400).toFixed(4));
            }

            fit();
            window.addEventListener('resize', fit);

            // Reached via the Download button, which links here with ?print=1.
            // Waits for load so the logo and the QR are painted before the
            // print snapshot is taken.
            // Read through dataset, and never name the attribute in full
            // anywhere else on the page: the dashed form then appears ONLY
            // when the attribute is really set, so "is auto-print off?" is
            // answerable. A script that mentioned it either way could not be
            // told apart from one that had it switched on.
            if (document.body.dataset.printOnLoad !== undefined) {
                window.addEventListener('load', function () { window.print(); });
            }
        })();
    </script>

</body>
</html>
