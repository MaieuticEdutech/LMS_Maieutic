<?php

declare(strict_types=1);

use App\Livewire\Admin\Courses\CoursesTable;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Livewire\Livewire;

it('lists courses by title', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Course::factory()->create(['title' => 'Introduction to Algebra']);
    Course::factory()->create(['title' => 'Advanced Chemistry']);

    Livewire::test(CoursesTable::class)
        ->assertSee('Introduction to Algebra')
        ->assertSee('Advanced Chemistry');
});

it('searches by title', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Course::factory()->create(['title' => 'Introduction to Algebra']);
    Course::factory()->create(['title' => 'Advanced Chemistry']);

    Livewire::test(CoursesTable::class)
        ->set('search', 'Algebra')
        ->assertSee('Introduction to Algebra')
        ->assertDontSee('Advanced Chemistry');
});

it('filters by status', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Course::factory()->published()->create(['title' => 'Published Course']);
    Course::factory()->create(['title' => 'Draft Course']); // status: draft, the factory default

    Livewire::test(CoursesTable::class)
        ->set('statusFilter', 'published')
        ->assertSee('Published Course')
        ->assertDontSee('Draft Course');
});

it('shows the course category when eager-loaded', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $category = Category::factory()->create(['name' => 'Mathematics']);
    Course::factory()->inCategory($category)->create(['title' => 'Introduction to Algebra']);

    Livewire::test(CoursesTable::class)->assertSee('Mathematics');
});

it('shows an empty state with no courses', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(CoursesTable::class)->assertSee('No courses yet');
});

it('links to the course builder for create and edit', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Course::factory()->create(['title' => 'Introduction to Algebra']);

    Livewire::test(CoursesTable::class)
        ->assertSee('Create course')
        ->assertSee(route('admin.courses.create'), false)
        ->assertSee(route('admin.courses.builder', Course::first()), false);
});

it('denies an instructor and a student from viewing the courses table', function (): void {
    Course::factory()->create();

    $instructor = User::factory()->instructor()->create();
    $this->actingAs($instructor)->get(route('admin.courses.index'))->assertForbidden();

    $student = User::factory()->student()->create();
    $this->actingAs($student)->get(route('admin.courses.index'))->assertForbidden();
});
