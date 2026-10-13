<?php

declare(strict_types=1);

use App\Actions\Enrollment\GrantEnrollment;
use App\Actions\Student\SetWeeklyGoal;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Livewire\Student\WeekPlanner;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\Student\WeekPlan;
use App\Services\Student\WeekPlanBlock;
use App\Services\Student\WeekPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| "Your week, planned" (WeekPlanService, SetWeeklyGoal, WeekPlanner)
|--------------------------------------------------------------------------
|
| "Now" is pinned to Wednesday 14 Oct 2026, 10:00 IST, so the IST week is
| Monday 12 Oct to Sunday 18 Oct 2026.
*/

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00', 'Asia/Kolkata'));

    $this->admin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();
    $this->student->forceFill(['weekly_goal_minutes' => 300])->save();

    // A published course with $count lessons of $minutes each, student enrolled.
    $this->makeCourse = function (int $count, int $minutes = 30, string $prefix = 'Lesson', bool $enrol = true): array {
        $course = Course::factory()->published()->create();
        $module = Module::factory()->forCourse($course)->published()->create();
        $lessons = [];
        for ($i = 0; $i < $count; $i++) {
            $lessons[] = Lesson::factory()->forModule($module)->published()->atPosition($i)
                ->create(['title' => "{$prefix} {$i}", 'duration_seconds' => $minutes * 60]);
        }
        $enrollment = $enrol
            ? app(GrantEnrollment::class)->handle($this->student, $course, EnrollmentSource::AdminGrant, $this->admin)
            : null;

        return [$course, $lessons, $enrollment];
    };

    $this->complete = fn (?Enrollment $e, Lesson $l, string $at) => LessonProgress::factory()->completed()->create([
        'enrollment_id' => $e?->id,
        'lesson_id' => $l->id,
        'user_id' => $this->student->id,
        'completed_at' => CarbonImmutable::parse($at, 'Asia/Kolkata')->utc(),
    ]);

    $this->plan = fn (): WeekPlan => app(WeekPlanService::class)->forStudent($this->student->refresh());

    /** @return list<WeekPlanBlock> */
    $this->blocksOn = function (WeekPlan $plan, string $date): array {
        foreach ($plan->days as $day) {
            if ($day->date->toDateString() === $date) {
                return $day->blocks;
            }
        }

        return [];
    };
});

it('has seven days, Monday to Sunday, with today flagged', function (): void {
    [$course] = ($this->makeCourse)(2);

    $plan = ($this->plan)();

    expect($plan->days)->toHaveCount(7)
        ->and($plan->days[0]->date->toDateString())->toBe('2026-10-12')
        ->and($plan->days[0]->date->format('D'))->toBe('Mon')
        ->and($plan->days[6]->date->toDateString())->toBe('2026-10-18');

    $today = collect($plan->days)->filter(fn ($d) => $d->isToday);
    expect($today)->toHaveCount(1)
        ->and($today->first()?->date->toDateString())->toBe('2026-10-14');
});

it('puts lessons completed this week on the right IST day', function (): void {
    [, $lessons, $enrollment] = ($this->makeCourse)(4);

    ($this->complete)($enrollment, $lessons[0], '2026-10-13 12:00:00'); // Tuesday

    $plan = ($this->plan)();
    $tuesday = ($this->blocksOn)($plan, '2026-10-13');

    expect($tuesday)->toHaveCount(1)
        ->and($tuesday[0]->done)->toBeTrue()
        ->and($tuesday[0]->title)->toBe('Lesson 0')
        ->and($plan->doneMinutes)->toBe(30);
});

it('draws the week boundary in IST, not UTC', function (): void {
    [, $lessons, $enrollment] = ($this->makeCourse)(4);

    // Sunday 23:30 IST = 18:00 UTC on the 11th: LAST week.
    ($this->complete)($enrollment, $lessons[0], '2026-10-11 23:30:00');
    // Monday 00:30 IST = 19:00 UTC on the 11th (same UTC date!): THIS week, Monday.
    ($this->complete)($enrollment, $lessons[1], '2026-10-12 00:30:00');

    $plan = ($this->plan)();
    $monday = ($this->blocksOn)($plan, '2026-10-12');

    expect($plan->doneMinutes)->toBe(30)
        ->and($monday)->toHaveCount(1)
        ->and($monday[0]->title)->toBe('Lesson 1');
});

it('fills upcoming lessons up to the goal and no further', function (): void {
    $this->student->forceFill(['weekly_goal_minutes' => 120])->save();
    ($this->makeCourse)(10, 30);

    $plan = ($this->plan)();
    $upcoming = collect($plan->days)->flatMap(fn ($d) => $d->blocks)->reject(fn ($b) => $b->done);

    expect($upcoming)->toHaveCount(4)
        ->and($plan->plannedMinutes())->toBe(120)
        // Nothing is planned in the past, and today comes first.
        ->and(($this->blocksOn)($plan, '2026-10-12'))->toBe([])
        ->and(($this->blocksOn)($plan, '2026-10-13'))->toBe([])
        ->and(count(($this->blocksOn)($plan, '2026-10-14')))->toBe(1);
});

it('counts finished minutes towards the goal so less is planned', function (): void {
    $this->student->forceFill(['weekly_goal_minutes' => 120])->save();
    [, $lessons, $enrollment] = ($this->makeCourse)(10, 30);

    ($this->complete)($enrollment, $lessons[0], '2026-10-13 12:00:00');
    ($this->complete)($enrollment, $lessons[1], '2026-10-13 13:00:00');

    $plan = ($this->plan)();
    $upcoming = collect($plan->days)->flatMap(fn ($d) => $d->blocks)->reject(fn ($b) => $b->done);

    expect($plan->doneMinutes)->toBe(60)
        ->and($upcoming)->toHaveCount(2)
        ->and($upcoming->first()?->title)->toBe('Lesson 2');
});

it('sizes a lesson with no duration at five minutes', function (): void {
    $this->student->forceFill(['weekly_goal_minutes' => 30])->save();
    [, $lessons] = ($this->makeCourse)(1);
    $lessons[0]->forceFill(['duration_seconds' => null])->save();

    $plan = ($this->plan)();

    expect($plan->plannedMinutes())->toBe(5);
});

it('shows an assessment in a planned lesson as a quiz block', function (): void {
    [, $lessons] = ($this->makeCourse)(1);
    Assessment::factory()->create([
        'assessable_type' => Lesson::class,
        'assessable_id' => $lessons[0]->id,
        'title' => 'Check yourself',
        'time_limit_minutes' => 15,
    ]);

    $blocks = collect(($this->plan)()->days)->flatMap(fn ($d) => $d->blocks);

    expect($blocks)->toHaveCount(1)
        ->and($blocks->first()?->isQuiz)->toBeTrue()
        ->and($blocks->first()?->title)->toBe('Check yourself')
        ->and($blocks->first()?->minutes)->toBe(15);
});

it('returns an empty week for a student with no enrolments', function (): void {
    $plan = ($this->plan)();

    expect($plan->hasCourses)->toBeFalse()
        ->and($plan->days)->toHaveCount(7)
        ->and($plan->hasAnyBlock())->toBeFalse()
        ->and($plan->doneMinutes)->toBe(0);
});

it('never plans a lesson from a course the student cannot access', function (): void {
    ($this->makeCourse)(3, 30, 'Mine');
    ($this->makeCourse)(3, 30, 'Stranger', enrol: false);

    [$suspendedCourse] = ($this->makeCourse)(3, 30, 'Suspended', enrol: false);
    Enrollment::factory()->create([
        'user_id' => $this->student->id,
        'course_id' => $suspendedCourse->id,
        'status' => EnrollmentStatus::Suspended,
    ]);

    $titles = collect(($this->plan)()->days)->flatMap(fn ($d) => $d->blocks)->pluck('title');

    expect($titles->filter(fn ($t) => str_starts_with($t, 'Mine')))->not->toBeEmpty()
        ->and($titles->filter(fn ($t) => str_starts_with($t, 'Stranger')))->toBeEmpty()
        ->and($titles->filter(fn ($t) => str_starts_with($t, 'Suspended')))->toBeEmpty();
});

it('never plans an unpublished lesson', function (): void {
    [, $lessons] = ($this->makeCourse)(2);
    $lessons[1]->forceFill(['is_published' => false])->save();

    $titles = collect(($this->plan)()->days)->flatMap(fn ($d) => $d->blocks)->pluck('title');

    expect($titles->all())->toBe(['Lesson 0']);
});

/*
| ═══════════ THE GOAL ═══════════
*/

it('lets a student set their own goal and audits it', function (): void {
    app(SetWeeklyGoal::class)->handle($this->student, 480, $this->student);

    expect($this->student->refresh()->weekly_goal_minutes)->toBe(480);
    expect(AuditLog::where('action', 'student.weekly_goal.changed')->count())->toBe(1);
});

it('refuses to let anyone else set a student\'s goal', function (): void {
    expect(fn () => app(SetWeeklyGoal::class)->handle($this->student, 480, $this->admin))
        ->toThrow(AuthorizationException::class);

    $other = User::factory()->create();
    expect(fn () => app(SetWeeklyGoal::class)->handle($this->student, 480, $other))
        ->toThrow(AuthorizationException::class);

    expect($this->student->refresh()->weekly_goal_minutes)->toBe(300);
});

it('rejects goals outside 30 minutes to 40 hours', function (int $minutes): void {
    expect(fn () => app(SetWeeklyGoal::class)->handle($this->student, $minutes, $this->student))
        ->toThrow(ValidationException::class);

    expect($this->student->refresh()->weekly_goal_minutes)->toBe(300);
})->with([0, 29, 2401, -5]);

it('accepts the boundary goals', function (int $minutes): void {
    app(SetWeeklyGoal::class)->handle($this->student, $minutes, $this->student);

    expect($this->student->refresh()->weekly_goal_minutes)->toBe($minutes);
})->with([30, 2400]);

it('is not mass assignable', function (): void {
    expect((new User)->isFillable('weekly_goal_minutes'))->toBeFalse();
});

/*
| ═══════════ THE COMPONENT ═══════════
*/

it('renders the empty state for a student with no enrolments', function (): void {
    $this->actingAs($this->student);

    Livewire::test(WeekPlanner::class)
        ->assertOk()
        ->assertSee('Your week, planned')
        ->assertSee('Nothing to plan yet');
});

it('renders the plan with the summary, today and player links', function (): void {
    [$course, $lessons, $enrollment] = ($this->makeCourse)(4);
    ($this->complete)($enrollment, $lessons[0], '2026-10-13 12:00:00');

    $this->actingAs($this->student);

    Livewire::test(WeekPlanner::class)
        ->assertOk()
        ->assertSee('30m of 5h this week')
        ->assertSee('Today')
        ->assertSee('Lesson 0')
        ->assertSee('Lesson 1')
        ->assertSee(route('student.courses.play', [$course, $lessons[1]]), false);
});

it('changes the goal from the component', function (): void {
    $this->actingAs($this->student);

    Livewire::test(WeekPlanner::class)
        ->call('setGoal', 120)
        ->assertSee('of 2h this week');

    expect($this->student->refresh()->weekly_goal_minutes)->toBe(120);

    $this->actingAs($this->student);

    Livewire::test(WeekPlanner::class)
        ->set('customHours', '6.5')
        ->call('setCustomGoal');

    expect($this->student->refresh()->weekly_goal_minutes)->toBe(390);
});

it('shows an error for an out-of-range custom goal', function (): void {
    $this->actingAs($this->student);

    Livewire::test(WeekPlanner::class)
        ->set('customHours', '100')
        ->call('setCustomGoal')
        ->assertHasErrors('customHours');

    expect($this->student->refresh()->weekly_goal_minutes)->toBe(300);
});
