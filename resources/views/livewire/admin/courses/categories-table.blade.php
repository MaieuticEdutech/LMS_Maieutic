{{--
    Category management.

    A flat table with children indented under their parent, rather than a drag
    tree: the hierarchy is one level deep and edited rarely, so a dropdown for
    the parent carries the same information for a fraction of the machinery.
--}}
<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <p class="text-sm text-neutral-500">
            Categories group courses in the public catalogue. A course may sit in one.
        </p>

        @can('create', App\Models\Category::class)
            <x-button wire:click="openCreate">Add category</x-button>
        @endcan
    </div>

    @if (session('status'))
        <x-alert variant="success" class="mb-4">{{ session('status') }}</x-alert>
    @endif

    @if ($categories->isEmpty())
        <x-empty-state
            title="No categories yet"
            description="Add a category so courses can be grouped in the catalogue."
        />
    @else
        <x-table>
            <x-slot:head>
                <th class="px-3 py-2">Name</th>
                <th class="px-3 py-2">Slug</th>
                <th class="px-3 py-2">Courses</th>
                <th class="px-3 py-2"><span class="sr-only">Actions</span></th>
            </x-slot:head>

            @foreach ($categories as $category)
                <tr wire:key="category-{{ $category->id }}">
                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $category->name }}</td>
                    <td class="px-3 py-2 font-mono text-xs text-neutral-500">{{ $category->slug }}</td>
                    <td class="px-3 py-2 text-neutral-500">{{ $category->courses_count }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">
                        @can('update', $category)
                            <button type="button" wire:click="openEdit({{ $category->id }})" class="text-teal-600 hover:underline">Edit</button>
                        @endcan
                        @can('delete', $category)
                            <button type="button" wire:click="confirmDelete({{ $category->id }})" class="ml-3 text-red-600 hover:underline">Delete</button>
                        @endcan
                    </td>
                </tr>

                @foreach ($category->children as $child)
                    <tr wire:key="category-{{ $child->id }}">
                        {{-- The dash is a text-level cue, not decoration: the
                             indent alone is invisible to a screen reader. --}}
                        <td class="px-3 py-2 pl-8 text-neutral-900">
                            <span class="text-neutral-400" aria-hidden="true">└</span>
                            {{ $child->name }}
                        </td>
                        <td class="px-3 py-2 font-mono text-xs text-neutral-500">{{ $child->slug }}</td>
                        <td class="px-3 py-2 text-neutral-500">{{ $child->courses_count }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @can('update', $child)
                                <button type="button" wire:click="openEdit({{ $child->id }})" class="text-teal-600 hover:underline">Edit</button>
                            @endcan
                            @can('delete', $child)
                                <button type="button" wire:click="confirmDelete({{ $child->id }})" class="ml-3 text-red-600 hover:underline">Delete</button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </x-table>
    @endif

    {{-- Create / edit.

         $nextTick for the same reason module-list.blade.php documents: Alpine
         initialises this wrapper before the nested <x-modal> binds its
         open-modal listener, so dispatching immediately fires into nothing. --}}
    @if ($showForm)
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'category-form'))">
            <x-modal name="category-form" :title="$editing ? 'Edit category' : 'Add category'">
                <form wire:submit="save" id="category-form" class="space-y-4">
                    <x-input wire:model="name" label="Category name" name="name" required />

                    <x-select wire:model="parent_id" label="Parent category (optional)" name="parent_id">
                        <option value="">Top level</option>
                        @foreach ($parentOptions as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </x-select>

                    <div>
                        <label for="category-description" class="block text-sm font-medium text-neutral-900">Description (optional)</label>
                        <textarea wire:model="description" id="category-description" rows="3" class="mt-1.5 block w-full rounded-control border border-neutral-200 px-3 py-2 text-sm text-neutral-900 hover:border-neutral-300"></textarea>
                    </div>
                </form>
                <x-slot:footer>
                    <x-button x-on:click="$dispatch('close-modal', 'category-form'); $wire.set('showForm', false)" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" form="category-form" size="sm">{{ $editing ? 'Save category' : 'Add category' }}</x-button>
                </x-slot:footer>
            </x-modal>
        </div>
    @endif

    {{-- Delete.

         States the consequence in numbers. Both foreign keys are nullOnDelete,
         so nothing is destroyed beyond the category itself — but "your courses
         become uncategorised" is a surprise worth spending a sentence on. --}}
    @if ($deleting)
        <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'delete-category'))">
            <x-modal name="delete-category" title="Delete category">
                <p class="text-sm text-neutral-600">
                    This deletes <span class="font-medium">{{ $deleting->name }}</span>.
                </p>

                @if ($deleting->courses_count > 0 || $deleting->children_count > 0)
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-neutral-600">
                        {{-- Each sentence built as ONE string rather than
                             stacked interpolations: Blade keeps the newlines
                             and indentation between them, so the rendered text
                             comes out with gaps in the middle of a sentence. --}}
                        @if ($deleting->courses_count > 0)
                            <li>{{ $deleting->courses_count }} {{ Str::plural('course', $deleting->courses_count) }} will be left uncategorised. No course is deleted.</li>
                        @endif
                        @if ($deleting->children_count > 0)
                            <li>{{ $deleting->children_count }} {{ Str::plural('subcategory', $deleting->children_count) }} will move to the top level.</li>
                        @endif
                    </ul>
                @endif

                <x-slot:footer>
                    <x-button wire:click="cancelDelete" x-on:click="$dispatch('close-modal', 'delete-category')" variant="secondary" size="sm">Cancel</x-button>
                    <x-button wire:click="delete" x-on:click="$dispatch('close-modal', 'delete-category')" variant="danger" size="sm">Delete category</x-button>
                </x-slot:footer>
            </x-modal>
        </div>
    @endif
</div>
