<?php

declare(strict_types=1);

use App\Livewire\Catalogue\Index;
use App\Models\Category;
use App\Models\Course;
use Livewire\Livewire;

it('shows published courses to a guest', function (): void {
    Course::factory()->published()->create(['title' => 'Published course']);

    Livewire::test(Index::class)->assertSee('Published course');
});

it('never shows a draft or archived course', function (): void {
    Course::factory()->create(['title' => 'Draft course']);
    Course::factory()->archived()->create(['title' => 'Archived course']);

    Livewire::test(Index::class)
        ->assertDontSee('Draft course')
        ->assertDontSee('Archived course');
});

it('searches by title', function (): void {
    Course::factory()->published()->create(['title' => 'Introduction to Algebra']);
    Course::factory()->published()->create(['title' => 'Advanced Chemistry']);

    Livewire::test(Index::class)
        ->set('search', 'Algebra')
        ->assertSee('Introduction to Algebra')
        ->assertDontSee('Advanced Chemistry');
});

it('filters by category', function (): void {
    $maths = Category::factory()->create(['name' => 'Mathematics']);
    $science = Category::factory()->create(['name' => 'Science']);

    Course::factory()->published()->inCategory($maths)->create(['title' => 'Algebra basics']);
    Course::factory()->published()->inCategory($science)->create(['title' => 'Chemistry basics']);

    Livewire::test(Index::class)
        ->set('category', $maths->slug)
        ->assertSee('Algebra basics')
        ->assertDontSee('Chemistry basics');
});

it('sorts by price', function (): void {
    Course::factory()->published()->pricedAt(500000)->create(['title' => 'Expensive']);
    Course::factory()->published()->pricedAt(10000)->create(['title' => 'Cheap']);

    $component = Livewire::test(Index::class)->set('sort', 'price-asc');

    $titles = $component->viewData('courses')->pluck('title')->all();

    expect($titles)->toBe(['Cheap', 'Expensive']);
});
