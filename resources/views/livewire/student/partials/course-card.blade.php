{{--
    One enrolled course, as the Spotlight × Canvas storefront card with the
    progress bar in place of the price. Shared by the dashboard and My Learning
    so the two cannot drift.

    The whole card is the link (stretched anchor inside x-store.course-card).

    Expects: $enrollment with `course` loaded together with category,
    thumbnail and instructors.instructorProfile.photo
    (StudentDashboardService::activeEnrollments does that; lazy loading is
    prevented outside production).
--}}
@php
    $course = $enrollment->course;
    $finished = $enrollment->completed_at !== null;
@endphp

<x-store.course-card
    :course="$course"
    :href="route('student.courses.play', $course)"
    :badge="$finished ? 'Completed' : null"
    :progress="(int) $enrollment->progress_percentage"
    :instructor-photo="$course->instructors->first()?->instructorProfile?->photoUrl()"
/>
