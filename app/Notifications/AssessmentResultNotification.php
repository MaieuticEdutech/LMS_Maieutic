<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Services\Settings\BrandingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your result for <assessment>" (FR-MAIL-07, architecture.md §14).
 *
 * Dispatched by SendAssessmentResultNotification from `AttemptGraded`, which
 * Phase 8 emits from both grading paths — a student pressing submit, and the
 * ExpireStaleAttempts sweep closing one that lapsed.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * A FAILED ATTEMPT GETS AN EMAIL TOO, AND THAT IS THE POINT.
 *
 * The obvious reading of "result email" is a congratulations message. But a
 * student who did not pass is the one who most needs to know where they stand
 * and that they can try again. Sending only on a pass would leave the more
 * important half of the audience with silence.
 *
 * The wording changes; the fact of sending does not.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * WHY THE PAYLOAD IS SCALARS, NOT THE ATTEMPT MODEL: queued notifications
 * serialise their constructor arguments, so a model would be re-fetched on the
 * worker and the email would describe the attempt's state at SEND time rather
 * than at GRADE time. A retake started in between would rewrite the result the
 * student is being told about. The facts are captured when the event fires and
 * travel with the job (FR-SYS-04) — the same rule EnrollmentGrantedNotification
 * follows, and for the same reason.
 */
class AssessmentResultNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $assessmentTitle,
        private readonly int $scorePercentage,
        private readonly bool $passed,
        private readonly string $attemptKey,
        private readonly bool $ranOutOfTime = false,
    ) {
        $this->onQueue(config()->string('lms.queues.mail'));
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $branding = app(BrandingService::class);
        $organisation = $branding->organisationName();

        $message = (new MailMessage)
            ->subject("Your result for {$this->assessmentTitle}")
            ->greeting("Hello, {$notifiable->name}");

        /*
         * An attempt closed by the timer is stated first and plainly. A student
         * who walked away and later receives a score with no explanation will
         * reasonably think something went wrong; saying so up front turns a
         * confusing email into an expected one (FR-ASMT-10).
         */
        if ($this->ranOutOfTime) {
            $message->line("Your attempt at **{$this->assessmentTitle}** was submitted automatically when its time ran out, and has been marked.");
        } else {
            $message->line("Your attempt at **{$this->assessmentTitle}** has been marked.");
        }

        $message->line("**You scored {$this->scorePercentage}%.**");

        $message->line($this->passed
            ? 'That is a pass — well done.'
            : 'That is below the pass mark for this assessment.');

        return $message
            ->action('See your full result', url(route('student.assessments.result', $this->attemptKey, absolute: false)))
            /*
             * Deliberately NOT "you have N attempts left". The attempt limit is
             * evaluated when a student starts one, against rules that can change
             * between this email being queued and being read — a number stated
             * here could be wrong by the time it is acted on. The result screen
             * knows the truth and is one click away.
             */
            ->line('Your full breakdown, question by question, is on the result page.')
            ->salutation("— The {$organisation} team")
            ->replyTo($branding->supportEmail());
    }
}
