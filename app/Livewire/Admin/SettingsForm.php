<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\UpdateSettings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', [
    'breadcrumbs' => [
        ['label' => 'Administration', 'url' => '/admin'],
        ['label' => 'Settings', 'url' => null],
    ],
])]
final class SettingsForm extends Component
{
    /**
     * Form state, NESTED by exploding every setting's dot-notated key on
     * '.' (Arr::undot). This is what lets one dynamic
     * wire:model="values.{{ $setting->key }}" bind correctly for whatever
     * settings actually exist in the table — Livewire resolves a dotted
     * wire:model path as nested array access, so the property shape has to
     * mirror that, not the flat "key => value" shape SettingsRepository
     * uses internally.
     *
     * @var array<string, mixed>
     */
    public array $values = [];

    /**
     * key => type ('string'|'integer'|'boolean'), read once in mount() so
     * save() can validate and coerce each field by its real column type
     * without re-querying or hardcoding the seeded key list.
     *
     * @var array<string, string>
     */
    public array $types = [];

    /**
     * Keys whose CURRENT value is a non-empty string. Only these are
     * required to stay non-empty on save — a setting seeded blank (e.g.
     * branding.logo_path) is allowed to stay blank.
     *
     * @var list<string>
     */
    public array $requiredStringKeys = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Setting::class);

        $settings = Setting::query()->orderBy('group')->orderBy('key')->get();

        $flat = $settings->mapWithKeys(static fn (Setting $setting): array => [$setting->key => $setting->value])->all();
        $this->values = Arr::undot($flat);

        $this->types = $settings->mapWithKeys(static fn (Setting $setting): array => [$setting->key => $setting->type])->all();

        $this->requiredStringKeys = array_values($settings
            ->filter(static fn (Setting $setting): bool => $setting->type === 'string' && $setting->value !== null && $setting->value !== '')
            ->map(static fn (Setting $setting): string => $setting->key)
            ->all());
    }

    public function save(UpdateSettings $updateSettings): void
    {
        $rules = [];

        foreach ($this->types as $key => $type) {
            $rules["values.{$key}"] = match ($type) {
                'integer' => ['required', 'numeric'],
                'boolean' => ['boolean'],
                default => in_array($key, $this->requiredStringKeys, true)
                    ? ['required', 'string']
                    : ['nullable', 'string'],
            };
        }

        $this->validate($rules);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $updateSettings->handle(Arr::dot($this->values), $actor);

        session()->flash('status', 'Settings updated.');
    }

    public function render(): View
    {
        $groups = Setting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group');

        return view('livewire.admin.settings-form', ['groups' => $groups]);
    }
}
