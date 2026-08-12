<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MediaPurpose;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Enrollment\EnrollmentAccessService;

/**
 * Authorisation for uploaded files — THE LAST GATE BEFORE THE BYTES
 * (FR-FILE-06, FR-FILE-07, AC-01, AC-02, AC-20).
 *
 * This is the most security-sensitive policy in the content domain. Every
 * video, PDF, presentation and downloadable resource passes through here, and
 * there is no other path to them: the `content` disk is private, is never
 * symlinked into public/, and has `serve => false` precisely so Laravel's
 * built-in local-disk route cannot bypass this class.
 *
 * DENY IS THE DEFAULT, INCLUDING ON FAILURE. If the owning course cannot be
 * resolved, the answer is NO. An unresolvable owner must never be treated as
 * "no restriction" — that inverts the whole model, and it is the single
 * likeliest way an access-control bug appears in a system like this.
 *
 * THUMBNAILS ARE THE ONE EXCEPTION, and a deliberate one: they appear on the
 * public catalogue by design (FR-STU-04), live on the public disk, and carry
 * no protected content. Every other purpose requires access to the course.
 */
final class MediaFilePolicy
{
    public function __construct(
        private readonly EnrollmentAccessService $access,
        private readonly CoursePolicy $coursePolicy,
    ) {}

    /**
     * Stream or download this file.
     *
     * Phase 6 calls this before issuing ANY signed URL — authorise first,
     * then issue a short-lived URL. Never the other way round.
     */
    public function access(?User $user, MediaFile $file): bool
    {
        // Course thumbnails are public by design (FR-STU-04).
        if ($file->purpose === MediaPurpose::Thumbnail) {
            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        $course = $file->resolveCourse();

        if ($course === null) {
            // Orphaned or attached to something whose owner we cannot
            // determine. DENY. See class docblock.
            return false;
        }

        // Admins and assigned instructors reach content for their courses;
        // students need an active enrollment. One definition, one place
        // (rule S-8).
        return $this->access->grantsAccess($user, $course);
    }

    /**
     * Alias used by media routes. Same rule — a separate name only so route
     * definitions read naturally.
     */
    public function stream(?User $user, MediaFile $file): bool
    {
        return $this->access($user, $file);
    }

    /**
     * Download as an attachment.
     *
     * Stricter than `access`: a file must ALSO be marked downloadable. Videos
     * are streamed and never offered as a download (FR-FILE-09), and that is
     * enforced here rather than by omitting a button — hiding a control is
     * not a control (Rule 20).
     */
    public function download(?User $user, MediaFile $file): bool
    {
        if (! $this->access($user, $file)) {
            return false;
        }

        if ($file->isStreamed() && ! $file->is_downloadable) {
            return false;
        }

        return true;
    }

    /**
     * Upload a new file against a course. Admin only in V1 — instructors do
     * not author content (FR-INS-08).
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, MediaFile $file): bool
    {
        return $this->manage($user, $file);
    }

    public function delete(User $user, MediaFile $file): bool
    {
        return $this->manage($user, $file);
    }

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    private function manage(User $user, MediaFile $file): bool
    {
        $course = $file->resolveCourse();

        if ($course === null) {
            // An orphan may only be removed by an admin — the cleanup job
            // runs as the system, not as a user.
            return $user->isSuperAdmin();
        }

        return $this->coursePolicy->manageContent($user, $course);
    }
}
