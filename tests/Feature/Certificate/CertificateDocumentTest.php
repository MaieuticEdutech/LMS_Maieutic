<?php

declare(strict_types=1);

use App\Actions\Certificate\IssueCertificate;
use App\Actions\Enrollment\GrantEnrollment;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Services\Certificate\CertificateDocument;
use App\Services\Settings\SettingsRepository;
use App\Support\Qr\QrSvg;

/*
|--------------------------------------------------------------------------
| The printable certificate (certificate/LMS_certificate_design.pptx)
|--------------------------------------------------------------------------
|
| Distinct from CertificateSurfacesTest, which covers the student's LIST and
| the public verification RECEIPT. This is the document itself: the designed
| sheet a student prints, with the QR code on it.
|
| Three things here are worth a test and the rest is layout:
|
|   WHO CAN OPEN IT. The receipt is public; this is not. It hands over a
|       print-ready certificate in someone's name, so it is authorised per
|       record.
|
|   WHAT THE QR ACTUALLY ENCODES. A QR code is unreadable to a reviewer — it
|       looks equally correct whatever is inside it. The tests below decode it
|       by construction and pin the payload.
|
|   THAT THE TEXT IS THE SNAPSHOT. Renaming a user must not rewrite a document
|       an employer has already verified.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();

    $this->student = User::factory()->create([
        'name' => 'Priya Sharma',
        'email' => 'priya@example.test',
    ]);

    $this->award = function (User $student, string $title = 'Python for Data Science'): Certificate {
        $course = Course::factory()->published()->create(['title' => $title]);

        $enrollment = app(GrantEnrollment::class)
            ->handle($student, $course, EnrollmentSource::AdminGrant, $this->admin);

        $enrollment->forceFill([
            'status' => EnrollmentStatus::Completed,
            'completed_at' => now(),
        ])->save();

        return app(IssueCertificate::class)->handle($enrollment->refresh());
    };

    /**
     * The exact <img> src a QR code for $data produces.
     *
     * The SVG carries nothing but the module grid, so this string is a pure
     * function of the payload — which makes it a precise way to assert what a
     * code on the page encodes, and what it does not.
     */
    $this->qrSrcFor = static function (string $data): string {
        $svg = app(QrSvg::class)->render($data, CertificateDocument::QR_SIZE);

        // An empty needle would make assertSee pass and assertDontSee fail for
        // reasons that have nothing to do with what the code encodes.
        if (! str_contains($svg, '<path d="')) {
            throw new RuntimeException('The rendered SVG contains no path — nothing was drawn.');
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    };
});

/*
| ═══════════════ WHO MAY OPEN IT ═══════════════
*/
it('shows a student their own certificate', function (): void {
    $certificate = ($this->award)($this->student);

    $this->actingAs($this->student)
        ->get(route('certificates.show', $certificate))
        ->assertOk()
        ->assertSee('Certificate of Completion')
        ->assertSee('Priya Sharma')
        ->assertSee('Python for Data Science')
        ->assertSee($certificate->number);
});

it('refuses one student another student\'s certificate', function (): void {
    // The number is the only thing standing between these two students, and it
    // is not enough here — unlike the public receipt, this page hands over a
    // print-ready credential.
    $other = ($this->award)(User::factory()->create(), 'Someone Else\'s Course');

    $this->actingAs($this->student)
        ->get(route('certificates.show', $other))
        ->assertForbidden();
});

it('lets a super admin open any certificate', function (): void {
    // Which is why this route sits OUTSIDE the role:student group — the policy
    // decides, not the middleware.
    $certificate = ($this->award)($this->student);

    $this->actingAs($this->admin)
        ->get(route('certificates.show', $certificate))
        ->assertOk()
        ->assertSee('Priya Sharma');
});

it('sends a guest to sign in', function (): void {
    $certificate = ($this->award)($this->student);

    $this->get(route('certificates.show', $certificate))
        ->assertRedirect(route('login'));
});

it('404s on a number that was never issued', function (): void {
    $this->actingAs($this->student)
        ->get('/certificates/MAI-CERT-ZZZZ-9999')
        ->assertNotFound();
});

/*
| ═══════════════ THE QR CODE ═══════════════
*/
it('encodes the public verification URL, not this page', function (): void {
    /*
     * The whole point of the code. Somebody scanning it off a printed sheet is
     * the person with the LEAST reason to have an account here, so it must
     * lead to the page that needs no account.
     *
     * Encoding this document's own URL instead would produce a code that is
     * indistinguishable by eye, works perfectly for the signed-in student
     * testing it, and dead-ends at a login screen for the employer it was put
     * there for.
     */
    $certificate = ($this->award)($this->student);

    $response = $this->actingAs($this->student)->get(route('certificates.show', $certificate));

    $response->assertOk()
        ->assertSee(($this->qrSrcFor)(route('certificates.verify', $certificate)), false)
        ->assertDontSee(($this->qrSrcFor)(route('certificates.show', $certificate)), false);
});

it('prints the verification URL beside the code for anyone typing it by hand', function (): void {
    $certificate = ($this->award)($this->student);

    $this->actingAs($this->student)
        ->get(route('certificates.show', $certificate))
        ->assertOk()
        ->assertSee((string) preg_replace('#^https?://#', '', route('certificates.verify', $certificate)));
});

it('embeds the code as an image rather than inline markup', function (): void {
    /*
     * Not cosmetic. dompdf skips an inline <svg> element in silence — no
     * shape, no warning — so the first version of the PDF came out with the
     * brand mark, the divider and THE QR CODE simply missing, while the same
     * template looked perfect in a browser. SVG reaches the PDF only through
     * an <img> src.
     */
    $certificate = ($this->award)($this->student);

    $this->actingAs($this->student)
        ->get(route('certificates.show', $certificate))
        ->assertOk()
        ->assertSee('<img class="qr" src="data:image/svg+xml;base64,', false)
        ->assertDontSee('<svg', false);
});

/*
| ═══════════════ SNAPSHOTS, NOT JOINS ═══════════════
*/
it('keeps the name it was awarded under after the student renames themselves', function (): void {
    $certificate = ($this->award)($this->student);

    $this->student->forceFill([
        'name' => 'Someone Entirely Different',
        'certificate_name' => 'Someone Entirely Different',
    ])->save();

    $this->actingAs($this->student)
        ->get(route('certificates.show', $certificate))
        ->assertOk()
        ->assertSee('Priya Sharma')
        ->assertDontSee('Someone Entirely Different');
});

it('keeps the course title it was awarded under after the course is retitled', function (): void {
    $certificate = ($this->award)($this->student);

    Course::query()->whereKey($certificate->course_id)->update(['title' => 'Renamed Course']);

    $this->actingAs($this->student)
        ->get(route('certificates.show', $certificate))
        ->assertOk()
        ->assertSee('Python for Data Science')
        ->assertDontSee('Renamed Course');
});

/*
| ═══════════════ ORGANISATION IDENTITY ═══════════════
*/
it('takes its organisation name and signatory from settings, never from the markup', function (): void {
    $certificate = ($this->award)($this->student);

    $settings = app(SettingsRepository::class);
    $settings->set('branding.organisation_name', 'Acme Institute', 'branding');
    $settings->set('branding.legal_name', 'Acme Institute Pvt. Ltd.', 'branding');
    $settings->set('branding.certificate_signatory', 'Ravishankar B.', 'branding');
    $settings->flush();

    $this->actingAs($this->student)
        ->get(route('certificates.show', $certificate))
        ->assertOk()
        ->assertSee('ACME INSTITUTE &middot; OFFICIAL RECORD', false)
        ->assertSee('ACME INSTITUTE PVT. LTD.')
        ->assertSee('Ravishankar B.')
        // The mono line under the rule is the same name, cased for the label.
        ->assertSee('RAVISHANKAR B')
        ->assertSee('<div class="signature">', false);
});

it('hides the signature block rather than inventing a signatory', function (): void {
    // An unset signatory must not become a plausible default. This document is
    // a claim the organisation makes about a person; a name printed under the
    // rule is a claim about who made it.
    $certificate = ($this->award)($this->student);

    app(SettingsRepository::class)->set('branding.certificate_signatory', '', 'branding');
    app(SettingsRepository::class)->flush();

    // The MARKUP, not the stylesheet — `.signature` is defined in both, and an
    // assertion that matched the CSS rule would pass whatever the page showed.
    $this->actingAs($this->student)
        ->get(route('certificates.show', $certificate))
        ->assertOk()
        ->assertDontSee('<div class="signature">', false)
        ->assertDontSee('<div class="signature-rule">', false);
});

/*
| ═══════════════ IT HAS TO PRINT ═══════════════
*/
/*
| ═══════════════ THE DOWNLOAD ═══════════════
|
| Download used to open the document and call window.print(). That left the
| student to notice their print Destination was still whatever they last
| printed to, and to untick "Headers and footers" — which overrides
| `@page { margin: 0 }` and stamps a URL and a page number across the artwork.
| A credential should not require its holder to configure a print dialog.
*/
it('returns a PDF file as an attachment', function (): void {
    $certificate = ($this->award)($this->student);

    $response = $this->actingAs($this->student)->get(route('certificates.download', $certificate));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        // `attachment`, not `inline`: inline opens the browser's PDF viewer and
        // leaves them hunting for a save button, which is the whole problem.
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$certificate->number.'.pdf"');

    expect((string) $response->getContent())->toStartWith('%PDF');
});

it('produces exactly one page', function (): void {
    /*
     * The sheet is 1400 x 990 px, which is the A-series ratio, and dompdf's DPI
     * is tuned so that lands a hair INSIDE A4 landscape. Landing exactly on the
     * boundary is how a one-page document silently becomes two with the second
     * page blank — and nobody looks at page two of a certificate before sending
     * it to an employer.
     */
    $certificate = ($this->award)($this->student);

    $pdf = app(CertificateDocument::class)->pdf($certificate);

    expect(preg_match_all('#/Type\s*/Page[^s]#', $pdf))->toBe(1);
});

it('names the file after the certificate number', function (): void {
    // "certificate.pdf" collides with every other certificate they have ever
    // been sent; the number is what they will search their downloads for.
    $certificate = ($this->award)($this->student);

    expect(app(CertificateDocument::class)->filename($certificate))
        ->toBe($certificate->number.'.pdf');
});

it('refuses one student another student\'s download', function (): void {
    // The document and the file it renders must never differ in who may have
    // them — this is the surface that actually hands the artefact over.
    $other = ($this->award)(User::factory()->create(), 'Someone Else\'s Course');

    $this->actingAs($this->student)
        ->get(route('certificates.download', $other))
        ->assertForbidden();
});

it('sends a guest asking for the file to sign in', function (): void {
    $certificate = ($this->award)($this->student);

    $this->get(route('certificates.download', $certificate))
        ->assertRedirect(route('login'));
});
