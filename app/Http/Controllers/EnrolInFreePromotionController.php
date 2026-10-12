<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Enrollment\EnrolInFreePromotion;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Enrol free" while the free-launch promotion is running (planning.md §16.4).
 *
 * A thin door onto EnrolInFreePromotion, which checks the promotion and hands
 * to GrantEnrollment. Idempotent: a second click lands in the player again
 * with no second enrollment and no second email.
 */
final class EnrolInFreePromotionController extends Controller
{
    public function __invoke(Request $request, Course $course, EnrolInFreePromotion $enrol): RedirectResponse
    {
        $student = $request->user();
        abort_unless($student instanceof User, 403);

        // Metadata visibility first (draft courses 404/403 here, as on the course page).
        $this->authorize('view', $course);

        $enrol->handle($student, $course);

        return redirect()
            ->route('student.courses.play', ['course' => $course])
            ->with('status', 'You are enrolled. Happy learning!');
    }
}
