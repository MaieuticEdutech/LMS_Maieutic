<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountActivationNotification;
use App\Notifications\AssessmentResultNotification;
use App\Notifications\CourseCompletedNotification;
use App\Notifications\EnrollmentGrantedNotification;
use App\Notifications\EnrollmentRevokedNotification;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders transactional emails in the browser for local development
 * (phases.md Phase 11, "mail preview routes for local development").
 *
 * WHY THIS IS WORTH HAVING: an email is the one part of the system a developer
 * cannot see by using the application. Without a preview the loop is "trigger
 * the real flow, open Mailpit, discover the branding is wrong, repeat", which
 * is slow enough that templates stop being checked.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * SECURITY: THIS CONTROLLER MUST NEVER BE REACHABLE IN PRODUCTION.
 *
 * It renders real templates with real organisation settings, so exposing it
 * would leak branding configuration and support addresses, and hand an
 * attacker a catalogue of exactly what this system's emails look like — the
 * raw material for a convincing phishing message aimed at students.
 *
 * Two independent guards, neither sufficient alone:
 *   1. The route is only registered when the environment is non-production
 *      AND lms.mail.preview_enabled is true (routes/web.php).
 *   2. This constructor aborts regardless of how the request arrived, so a
 *      cached route table or a mistaken registration still fails closed.
 *
 * Nothing here is queued or sent. Previews render to the browser; a preview
 * route that could actually deliver mail would be an open relay aimed at any
 * address a caller supplied.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class MailPreviewController extends Controller
{
    /**
     * Every previewable email, keyed by the slug used in the URL.
     *
     * Adding an email to this list is how a later phase makes it previewable;
     * the notification is built with obviously fake data so a preview can
     * never be confused with a real send.
     *
     * Each factory returns the rendered MailMessage rather than the
     * notification, because `toMail()` is declared by the individual
     * notification classes, not by the base Notification — the return type
     * here is the honest one.
     *
     * @return array<string, callable(User): MailMessage>
     */
    private function previews(): array
    {
        return [
            'verify-email' => static fn (User $user): MailMessage => (new VerifyEmailNotification)->toMail($user),
            'reset-password' => static fn (User $user): MailMessage => (new ResetPasswordNotification('preview-token-not-a-real-token'))->toMail($user),
            'account-activation' => static fn (User $user): MailMessage => (new AccountActivationNotification('preview-token-not-a-real-token'))->toMail($user),
            'password-changed' => static fn (User $user): MailMessage => (new PasswordChangedNotification)->toMail($user),

            /*
             * Both enrollment emails have two forms that read very differently,
             * and both are previewable — the variant is the part most likely to
             * be got wrong, and the one a reviewer most needs to see.
             */
            'enrollment-granted' => static fn (User $user): MailMessage => (new EnrollmentGrantedNotification('Example Course'))->toMail($user),
            'enrollment-reactivated' => static fn (User $user): MailMessage => (new EnrollmentGrantedNotification('Example Course', wasReactivated: true, expiresAt: CarbonImmutable::now()->addMonths(6)))->toMail($user),
            'enrollment-revoked' => static fn (User $user): MailMessage => (new EnrollmentRevokedNotification('Example Course', 'Refund processed.'))->toMail($user),
            'enrollment-expired' => static fn (User $user): MailMessage => (new EnrollmentRevokedNotification('Example Course', 'Access period ended.', wasAutomatic: true))->toMail($user),

            /*
             * Phase 11's last two, unblocked by Phases 8 and 9. Both have
             * variants that read very differently and both are previewable —
             * a pass and a fail are not the same email with a different number
             * in it, and the failing one is the harder to get right.
             */
            'assessment-passed' => static fn (User $user): MailMessage => (new AssessmentResultNotification('Example Quiz', 82, true, 'preview-attempt-key'))->toMail($user),
            'assessment-failed' => static fn (User $user): MailMessage => (new AssessmentResultNotification('Example Quiz', 41, false, 'preview-attempt-key'))->toMail($user),
            'assessment-timed-out' => static fn (User $user): MailMessage => (new AssessmentResultNotification('Example Quiz', 36, false, 'preview-attempt-key', ranOutOfTime: true))->toMail($user),
            'course-completed' => static fn (User $user): MailMessage => (new CourseCompletedNotification('Example Course', 12))->toMail($user),
        ];
    }

    public function __construct()
    {
        // Guard 2 — fails closed however the request reached this class.
        abort_if(app()->isProduction(), 404);
        abort_unless(config()->boolean('lms.mail.preview_enabled', false), 404);
    }

    /**
     * List the available previews.
     */
    public function index(): Response
    {
        /*
         * Built as a relative path rather than through route() so the index
         * does not depend on the route NAME being registered — the controller
         * is the thing under test, and a named-route lookup would make this
         * page fail for a reason unrelated to previews.
         */
        $links = collect(array_keys($this->previews()))
            ->map(static fn (string $slug): string => sprintf(
                '<li><a href="%s">%s</a></li>',
                e(url('dev/mail/'.$slug)),
                e($slug),
            ))
            ->implode('');

        return response(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            .'<title>Mail previews</title></head><body>'
            .'<h1>Transactional email previews</h1><ul>'.$links.'</ul>'
            .'</body></html>',
        );
    }

    /**
     * Render one email exactly as a recipient would receive it.
     */
    public function show(string $email): Response
    {
        $factory = $this->previews()[$email] ?? throw new NotFoundHttpException("No preview named [{$email}].");

        /*
         * An UNSAVED user, deliberately. A preview must not create or mutate
         * data, and must not require a matching row to exist in whatever state
         * the developer's database happens to be in.
         */
        $user = new User([
            'name' => 'Preview Student',
            'email' => 'preview@example.test',
        ]);
        $user->id = 0;

        // render() returns Htmlable; response() wants a string.
        return response((string) $factory($user)->render());
    }
}
