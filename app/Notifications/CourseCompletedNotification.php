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
 * "You have completed <course>" (FR-MAIL-07, architecture.md §14).
 *
 * Dispatched by SendCourseCompletedNotification from Phase 9's `CourseCompleted`
 * event, which fires on the TRANSITION only — a recalculation that finds the
 * course still finished does not re-fire it. That guard is what stops a student
 * being congratulated three times because an author republished a lesson.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * IT PROMISES NOTHING IT CANNOT DELIVER.
 *
 * No certificate is attached and none is mentioned. Certificates are not built
 * in V1, and an email saying "your certificate is on its way" would be a
 * promise the product does not keep — the most damaging kind of automated mail
 * there is, because the student has no way to tell it is a mistake.
 *
 * What it does say is true and worth saying: the course is finished, and access
 * does not end because of it (EnrollmentAccessService still grants on
 * `completed`). Someone who has just finished a course they paid for is exactly
 * the person who wonders whether they are about to lose it.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * Scalars, not the Enrollment model, for the reason set out in
 * EnrollmentGrantedNotification: a queued notification re-fetches a serialised
 * model on the worker and would describe the enrollment as it is at SEND time.
 */
class CourseCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $courseTitle,
        private readonly int $lessonsCompleted = 0,
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
            ->subject("You have completed {$this->courseTitle}")
            ->greeting("Congratulations, {$notifiable->name}")
            ->line("You have completed **{$this->courseTitle}**.");

        // Only when there is a real figure to state. "You completed 0 lessons"
        // is worse than saying nothing about lessons at all.
        if ($this->lessonsCompleted > 0) {
            $message->line(sprintf(
                'That is all %d %s, and every assessment the course required.',
                $this->lessonsCompleted,
                $this->lessonsCompleted === 1 ? 'lesson' : 'lessons',
            ));
        }

        return $message
            ->line('The course stays in your library — you can revisit any lesson whenever you want to.')
            ->action('Back to my courses', url(route('student.courses.index', absolute: false)))
            ->salutation("— The {$organisation} team")
            ->replyTo($branding->supportEmail());
    }
}
