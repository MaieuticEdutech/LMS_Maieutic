<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Livewire\Admin\Courses\CourseBuilder;
use App\Livewire\Admin\SettingsForm;
use App\Livewire\Admin\StudentForm;
use App\Livewire\Admin\StudentsTable;
use App\Models\Course;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| QA PROBE — adversarial input, mass assignment, boundaries
|--------------------------------------------------------------------------
|
| Plan IDs: STU-05, STU-06, STU-22 … STU-28, STU-34 … STU-36, CRS-10 … CRS-19,
| SEC-03 … SEC-07, SET-06 … SET-10.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->actingAs($this->admin);

    // SettingsForm builds its validation rules from the rows it finds, and
    // RefreshDatabase rolls back the seeded settings.
    $this->seed(Database\Seeders\SettingsSeeder::class);
});

/*
| ═══════════ STU-05 / STU-06 — hostile search input ═══════════
*/

it('treats a sql injection payload in search as a literal string', function (string $payload): void {
    User::factory()->count(3)->create();

    Livewire::test(StudentsTable::class)
        ->set('search', $payload)
        ->assertOk();

    // The table it would have dropped is still there.
    expect(User::query()->count())->toBeGreaterThan(0);
})->with([
    "'; DROP TABLE users;--",
    "' OR '1'='1",
    '1; DELETE FROM users WHERE 1=1;--',
    "%' UNION SELECT * FROM settings--",
]);

it('escapes an xss payload stored in a student name', function (): void {
    $payload = '<script>alert(1)</script>';

    User::factory()->create(['name' => $payload]);

    $html = Livewire::test(StudentsTable::class)
        ->html();

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('escapes an image onerror payload in a student name', function (): void {
    User::factory()->create(['name' => '<img src=x onerror=alert(1)>']);

    $html = Livewire::test(StudentsTable::class)->html();

    expect($html)->not->toContain('<img src=x onerror=alert(1)>');
});

it('handles a null byte and unicode in search without erroring', function (string $payload): void {
    Livewire::test(StudentsTable::class)
        ->set('search', $payload)
        ->assertOk();
})->with(["null\0byte", 'Śrīvatsa 中文 🎓', str_repeat('a', 5000)]);

/*
| ═══════════ STU-22 … STU-34 — student form boundaries ═══════════
*/

it('rejects an overlong student name and accepts the boundary', function (): void {
    $component = Livewire::test(StudentForm::class);

    $component->set('name', str_repeat('a', 256))->set('email', 'a@b.test')
        ->call('save')->assertHasErrors('name');

    $component->set('name', str_repeat('a', 255))->set('email', 'a@b.test')
        ->call('save')->assertHasNoErrors('name');
});

it('rejects an overlong phone number', function (): void {
    Livewire::test(StudentForm::class)
        ->set('name', 'Valid')
        ->set('email', 'valid@b.test')
        ->set('phone', str_repeat('9', 31))
        ->call('save')
        ->assertHasErrors('phone');
});

it('rejects a whitespace only student name', function (): void {
    Livewire::test(StudentForm::class)
        ->set('name', '   ')
        ->set('email', 'ws@b.test')
        ->call('save')
        ->assertHasErrors('name');
});

it('rejects a duplicate email', function (): void {
    User::factory()->create(['email' => 'taken@lms.test']);

    Livewire::test(StudentForm::class)
        ->set('name', 'Someone')
        ->set('email', 'taken@lms.test')
        ->call('save')
        ->assertHasErrors('email');
});

it('accepts a unicode student name', function (): void {
    Livewire::test(StudentForm::class)
        ->set('name', 'Śrīvatsa 中文')
        ->set('email', 'unicode@b.test')
        ->call('save')
        ->assertHasNoErrors('name');
});

/*
| ═══════════ SEC-03 / SEC-04 — privilege escalation via mass assignment ═══════════
*/

it('refuses to make role fillable on a user', function (): void {
    $user = User::factory()->create();

    expect(fn () => $user->fill(['role' => 'super_admin']))->toThrow(MassAssignmentException::class);
});

it('refuses to make status fillable on a user', function (): void {
    $user = User::factory()->create();

    expect(fn () => $user->fill(['status' => UserStatus::Active->value]))->toThrow(MassAssignmentException::class);
});

it('keeps a student created through the admin form out of the super admin role', function (): void {
    Livewire::test(StudentForm::class)
        ->set('name', 'Ordinary')
        ->set('email', 'ordinary@b.test')
        ->call('save');

    $created = User::query()->where('email', 'ordinary@b.test')->firstOrFail();

    expect($created->isSuperAdmin())->toBeFalse()
        ->and($created->isStudent())->toBeTrue();
});

/*
| ═══════════ CRS-12 … CRS-19 — course price and level boundaries ═══════════
*/

it('rejects a zero or negative course price', function (string $price): void {
    Livewire::test(CourseBuilder::class)
        ->set('title', 'Priced course')
        ->set('level', 'beginner')
        ->set('language', 'en')
        ->set('priceRupees', $price)
        ->call('save')
        ->assertHasErrors('priceRupees');
})->with(['0', '-100', '0.00']);

it('accepts the minimum legal course price', function (): void {
    Livewire::test(CourseBuilder::class)
        ->set('title', 'Cheap course')
        ->set('level', 'beginner')
        ->set('language', 'en')
        ->set('priceRupees', '0.01')
        ->call('save')
        ->assertHasNoErrors('priceRupees');
});

it('rejects a non numeric course price', function (): void {
    Livewire::test(CourseBuilder::class)
        ->set('title', 'Bad price')
        ->set('level', 'beginner')
        ->set('language', 'en')
        ->set('priceRupees', 'abc')
        ->call('save')
        ->assertHasErrors('priceRupees');
});

it('rejects a tampered course level', function (): void {
    Livewire::test(CourseBuilder::class)
        ->set('title', 'Bad level')
        ->set('level', 'expert-plus')
        ->set('language', 'en')
        ->set('priceRupees', '100')
        ->call('save')
        ->assertHasErrors('level');
});

it('rejects an overlong language code', function (): void {
    Livewire::test(CourseBuilder::class)
        ->set('title', 'Bad language')
        ->set('level', 'beginner')
        ->set('language', str_repeat('e', 11))
        ->set('priceRupees', '100')
        ->call('save')
        ->assertHasErrors('language');
});

it('rejects a non existent category id', function (): void {
    Livewire::test(CourseBuilder::class)
        ->set('title', 'Bad category')
        ->set('level', 'beginner')
        ->set('language', 'en')
        ->set('priceRupees', '100')
        ->set('category_id', 999999)
        ->call('save')
        ->assertHasErrors('category_id');
});

it('escapes an xss payload in a course title on the admin table', function (): void {
    Course::factory()->create(['title' => '<script>alert("course")</script>']);

    $html = Livewire::test(App\Livewire\Admin\Courses\CoursesTable::class)
        ->html();

    expect($html)->not->toContain('<script>alert("course")</script>');
});

/*
| ═══════════ SET-06 … SET-10 — settings validation floors and caps ═══════════
*/

it('rejects a non numeric value for an integer setting', function (): void {
    $component = Livewire::test(SettingsForm::class);

    $component->set('values.learning.video_completion_threshold', 'abc')
        ->call('save')
        ->assertHasErrors('values.learning.video_completion_threshold');
});

it('records whether an out of range completion threshold is accepted', function (int $value): void {
    Livewire::test(SettingsForm::class)
        ->set('values.learning.video_completion_threshold', $value)
        ->call('save');

    $stored = Setting::query()->where('key', 'learning.video_completion_threshold')->firstOrFail();

    // Documents actual behaviour: a percentage setting with no 0-100 clamp.
    expect((int) $stored->value)->toBe($value);
})->with([-10, 150, 999]);
