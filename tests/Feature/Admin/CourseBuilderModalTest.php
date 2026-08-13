<?php

declare(strict_types=1);

use App\Livewire\Admin\Courses\LessonList;
use App\Livewire\Admin\Courses\ModuleList;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Course Builder · the add-module and add-lesson modals
|--------------------------------------------------------------------------
|
| WHAT THESE CATCH, AND WHAT THEY CANNOT.
|
| "Add module" was broken in the browser while the component underneath worked
| perfectly: the click set showForm, the markup rendered, and no dialog
| appeared. The cause was Alpine ordering — the wrapper's x-init dispatched
| `open-modal` before the <x-modal> nested inside it had bound its window
| listener, so the event was sent to nobody.
|
| These tests assert the SERVER side of that flow: the action exists, it flips
| the state, and the modal markup with its listener reaches the page. That is
| the part that can regress silently through a rename or a refactor.
|
| They cannot catch the ordering bug itself — Alpine does not run in Pest.
| Only a browser can prove the dialog opens, which is why the fix carries its
| reasoning in a comment beside the code rather than relying on a test to
| explain it.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->course = Course::factory()->create();
    $this->actingAs($this->admin);
});

it('opens the module form and renders the modal with its listener', function (): void {
    Livewire::test(ModuleList::class, ['course' => $this->course])
        ->assertSet('showForm', false)
        ->call('openCreate')
        ->assertSet('showForm', true)
        // The dialog and the listener that opens it must both reach the page.
        ->assertSee('open-modal', false)
        ->assertSee('x-on:open-modal.window', false);
});

it('defers the open dispatch to the next tick', function (): void {
    // The regression guard for the actual defect. Without $nextTick the event
    // fires before the modal listens, and the dialog never appears.
    Livewire::test(ModuleList::class, ['course' => $this->course])
        ->call('openCreate')
        ->assertSee('$nextTick(() => $dispatch(\'open-modal\'', false);
});

it('creates a module through the form', function (): void {
    Livewire::test(ModuleList::class, ['course' => $this->course])
        ->call('openCreate')
        ->set('title', 'Foundations')
        ->call('save')
        ->assertHasNoErrors();

    // Held as a local so the assertions read one known row rather than
    // re-querying and walking a nullable result twice.
    $module = $this->course->modules()->sole();

    expect($this->course->modules()->count())->toBe(1)
        ->and($module->title)->toBe('Foundations')
        // Draft by default, so building in a live catalogue leaks nothing
        // (FR-CNT-05).
        ->and($module->is_published)->toBeFalse();
});

it('closes the form after a successful save', function (): void {
    Livewire::test(ModuleList::class, ['course' => $this->course])
        ->call('openCreate')
        ->set('title', 'Foundations')
        ->call('save')
        ->assertSet('showForm', false);
});

it('keeps the form open when the title is missing', function (): void {
    // A validation failure must not dismiss the dialog and lose what was typed.
    Livewire::test(ModuleList::class, ['course' => $this->course])
        ->call('openCreate')
        ->set('title', '')
        ->call('save')
        ->assertHasErrors('title')
        ->assertSet('showForm', true);
});

it('opens the lesson form and defers its dispatch too', function (): void {
    $module = Module::factory()->forCourse($this->course)->create();

    Livewire::test(LessonList::class, ['module' => $module])
        ->call('openCreate')
        ->assertSet('showForm', true)
        ->assertSee('$nextTick(() => $dispatch(\'open-modal\'', false);
});
