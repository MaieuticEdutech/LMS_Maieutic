<?php

declare(strict_types=1);

use App\Livewire\Admin\Courses\CategoriesTable;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Category management (FR-CNT-15)
|--------------------------------------------------------------------------
|
| The table, model, policy and factory shipped in Phase 3 and the Course
| Builder has always offered the field, but nothing could populate the list.
| These cover the screen that closes that gap.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->actingAs($this->admin);
});

/*
| ═══════════ Access ═══════════
*/

it('refuses a student the categories screen', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.categories.index'))
        ->assertForbidden();
});

it('refuses an instructor the categories screen', function (): void {
    $this->actingAs(User::factory()->instructor()->create())
        ->get(route('admin.categories.index'))
        ->assertForbidden();
});

it('redirects a guest', function (): void {
    auth()->logout();

    $this->get(route('admin.categories.index'))->assertRedirect();
});

it('serves the screen to a super admin', function (): void {
    $this->get(route('admin.categories.index'))->assertOk();
});

/*
| ═══════════ Create ═══════════
*/

it('shows an empty state before any category exists', function (): void {
    Livewire::test(CategoriesTable::class)->assertSee('No categories yet');
});

it('creates a category and derives a slug from the name', function (): void {
    Livewire::test(CategoriesTable::class)
        ->call('openCreate')
        ->set('name', 'Data Engineering')
        ->call('save')
        ->assertHasNoErrors();

    $category = Category::query()->firstOrFail();

    expect($category->name)->toBe('Data Engineering')
        ->and($category->slug)->toBe('data-engineering')
        ->and($category->parent_id)->toBeNull()
        ->and($category->position)->toBe(0);
});

it('rejects an empty name and an overlong one', function (): void {
    $c = Livewire::test(CategoriesTable::class)->call('openCreate');

    $c->set('name', '')->call('save')->assertHasErrors('name');
    $c->set('name', str_repeat('a', 256))->call('save')->assertHasErrors('name');
    $c->set('name', str_repeat('a', 255))->call('save')->assertHasNoErrors('name');
});

it('uniquifies a slug when two categories share a name', function (): void {
    foreach (['Design', 'Design'] as $name) {
        Livewire::test(CategoriesTable::class)
            ->call('openCreate')
            ->set('name', $name)
            ->call('save');
    }

    expect(Category::query()->pluck('slug')->all())->toBe(['design', 'design-2']);
});

it('numbers siblings independently of the other branch', function (): void {
    $parent = Category::factory()->create(['parent_id' => null, 'position' => 0]);

    Livewire::test(CategoriesTable::class)
        ->call('openCreate')
        ->set('name', 'First child')
        ->set('parent_id', $parent->id)
        ->call('save');

    Livewire::test(CategoriesTable::class)
        ->call('openCreate')
        ->set('name', 'Second child')
        ->set('parent_id', $parent->id)
        ->call('save');

    $positions = Category::query()->where('parent_id', $parent->id)->orderBy('position')->pluck('position');

    expect($positions->all())->toBe([0, 1]);
});

it('rejects a parent that does not exist', function (): void {
    Livewire::test(CategoriesTable::class)
        ->call('openCreate')
        ->set('name', 'Orphan')
        ->set('parent_id', 999999)
        ->call('save')
        ->assertHasErrors('parent_id');
});

/*
| ═══════════ Edit ═══════════
*/

it('edits a category without rewriting its slug', function (): void {
    $category = Category::factory()->create(['name' => 'Old name', 'slug' => 'old-name']);

    Livewire::test(CategoriesTable::class)
        ->call('openEdit', $category->id)
        ->set('name', 'New name')
        ->call('save')
        ->assertHasNoErrors();

    // The slug is the public route key — rewriting it would break links
    // already in the wild.
    expect($category->refresh())
        ->name->toBe('New name')
        ->slug->toBe('old-name');
});

it('refuses to make a category its own parent', function (): void {
    $category = Category::factory()->create();

    Livewire::test(CategoriesTable::class)
        ->call('openEdit', $category->id)
        ->set('parent_id', $category->id)
        ->call('save')
        ->assertHasErrors('parent_id');

    expect($category->refresh()->parent_id)->toBeNull();
});

it('refuses to move a category inside its own descendant', function (): void {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    // Would make parent -> child -> parent, and every later tree walk would
    // recurse until it ran out of memory.
    Livewire::test(CategoriesTable::class)
        ->call('openEdit', $parent->id)
        ->set('parent_id', $child->id)
        ->call('save')
        ->assertHasErrors('parent_id');

    expect($parent->refresh()->parent_id)->toBeNull();
});

it('never offers the edited category as its own parent option', function (): void {
    $category = Category::factory()->create(['name' => 'Self Referencing']);

    Livewire::test(CategoriesTable::class)
        ->call('openEdit', $category->id)
        ->assertViewHas('parentOptions', fn ($options) => $options->doesntContain('id', $category->id));
});

/*
| ═══════════ Delete ═══════════
*/

it('deletes only on confirmation', function (): void {
    $category = Category::factory()->create();

    Livewire::test(CategoriesTable::class)
        ->call('confirmDelete', $category->id)
        ->call('cancelDelete');

    expect(Category::query()->count())->toBe(1);
});

it('deletes a category and leaves its courses uncategorised', function (): void {
    $category = Category::factory()->create();
    $course = Course::factory()->create(['category_id' => $category->id]);

    Livewire::test(CategoriesTable::class)
        ->call('confirmDelete', $category->id)
        ->call('delete');

    // nullOnDelete: the course survives, uncategorised. Deleting a browsing
    // aid must never delete a course.
    expect(Category::query()->count())->toBe(0)
        ->and($course->refresh()->category_id)->toBeNull()
        ->and($course->exists)->toBeTrue();
});

it('promotes children to the top level when their parent is deleted', function (): void {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    Livewire::test(CategoriesTable::class)
        ->call('confirmDelete', $parent->id)
        ->call('delete');

    expect($child->refresh()->parent_id)->toBeNull()
        ->and($child->exists)->toBeTrue();
});

it('states the consequences before deleting', function (): void {
    $category = Category::factory()->create(['name' => 'Busy Category']);
    Course::factory()->count(2)->create(['category_id' => $category->id]);
    Category::factory()->create(['parent_id' => $category->id]);

    Livewire::test(CategoriesTable::class)
        ->call('confirmDelete', $category->id)
        ->assertSee('2 courses will be left uncategorised')
        ->assertSee('1 subcategory will move to the top level');
});

/*
| ═══════════ The gap this closes ═══════════
*/

it('offers a created category to the course builder', function (): void {
    Livewire::test(CategoriesTable::class)
        ->call('openCreate')
        ->set('name', 'Engineering')
        ->call('save');

    Livewire::test(App\Livewire\Admin\Courses\CourseBuilder::class)
        ->assertSee('Engineering');
});

it('records every category change in the audit log', function (): void {
    Livewire::test(CategoriesTable::class)
        ->call('openCreate')
        ->set('name', 'Audited')
        ->call('save');

    $category = Category::query()->firstOrFail();

    Livewire::test(CategoriesTable::class)
        ->call('openEdit', $category->id)
        ->set('name', 'Audited twice')
        ->call('save');

    Livewire::test(CategoriesTable::class)
        ->call('confirmDelete', $category->id)
        ->call('delete');

    expect(DB::table('audit_logs')->whereIn('action', [
        'category.created', 'category.updated', 'category.deleted',
    ])->count())->toBe(3);
});
