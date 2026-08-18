{{--
    Bulk question import.

    Three states in one modal: pick a file, review what was parsed, import.
    The review table is the substance — see the component docblock for why it
    is not optional.
--}}
<div>
    <button
        type="button"
        wire:click="open"
        x-on:click="$nextTick(() => $dispatch('open-modal', 'question-import'))"
        class="rounded-sm border border-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:border-neutral-300 hover:text-teal-700"
    >
        Import from Excel
    </button>

    {{-- $nextTick above: Alpine initialises a parent before its children, so a
         dispatch fired during init reaches a modal that has not registered its
         listener yet. Deferring one tick is what makes it open — this has
         caught us before on this exact screen. --}}
    <x-modal name="question-import" title="Import questions from a spreadsheet" width="4xl">

        @if ($candidates === [])
            {{-- ══ STATE 1: choose a file ══ --}}
            <div class="space-y-4">
                <p class="text-sm text-neutral-600">
                    One question per row. The first row must name the columns:
                    <span class="font-mono text-xs text-neutral-800">Question</span>,
                    <span class="font-mono text-xs text-neutral-800">Option A</span>,
                    <span class="font-mono text-xs text-neutral-800">Option B</span>, …,
                    <span class="font-mono text-xs text-neutral-800">Answer</span>, and optionally
                    <span class="font-mono text-xs text-neutral-800">Explanation</span>.
                </p>

                <p class="text-sm text-neutral-600">
                    The <span class="font-medium">Answer</span> cell holds the option letter — <span class="font-mono text-xs">B</span>,
                    or <span class="font-mono text-xs">B,D</span> where more than one is correct. Add as many
                    <span class="font-mono text-xs text-neutral-800">Option</span> columns as your questions need;
                    a blank option cell simply means that question has fewer.
                </p>

                <x-input
                    type="file"
                    label="Spreadsheet"
                    name="file"
                    wire:model="file"
                    accept=".xlsx"
                    hint="Excel .xlsx, up to 5 MB and {{ \App\Services\Assessment\QuestionImportParser::MAX_QUESTIONS }} questions."
                />

                <div wire:loading wire:target="file" class="text-sm text-neutral-500">Reading the spreadsheet&hellip;</div>

                @error('file')
                    <x-alert variant="danger">{{ $message }}</x-alert>
                @enderror

                @if ($fileError)
                    <x-alert variant="danger">{{ $fileError }}</x-alert>
                @endif
            </div>
        @else
            {{-- ══ STATE 2: review ══ --}}
            <div class="space-y-5">

                @error('candidates')
                    <x-alert variant="danger">{{ $message }}</x-alert>
                @enderror

                @if ($problems !== [])
                    {{-- Named by spreadsheet row so the author can go and fix
                         them. These rows are simply absent from the table
                         below — they are not importable and there is nothing
                         useful to show. --}}
                    <x-alert variant="warning">
                        <p class="font-medium">{{ count($problems) }} {{ Str::plural('row', count($problems)) }} could not be read and {{ count($problems) === 1 ? 'is' : 'are' }} not listed below:</p>
                        <ul class="mt-2 list-inside list-disc space-y-1 text-sm">
                            @foreach ($problems as $problem)
                                <li>Row {{ $problem['row'] }} — {{ $problem['message'] }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                {{-- ══ SET FOR ALL ══ the file carries what each question IS;
                     marks and type are the author's call. --}}
                <div class="flex flex-wrap items-end gap-3 rounded-md border border-neutral-200 bg-neutral-50 p-4">
                    <div class="w-44">
                        <x-select label="Type for all" name="defaultType" wire:model="defaultType">
                            @foreach ($questionTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    <button type="button" wire:click="applyTypeToAll" class="mb-1 rounded-sm border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                        Apply to all
                    </button>

                    <div class="w-28">
                        <x-input type="number" step="0.5" min="0.5" label="Marks for all" name="defaultMarks" wire:model="defaultMarks" />
                    </div>

                    <button type="button" wire:click="applyMarksToAll" class="mb-1 rounded-sm border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">
                        Apply to all
                    </button>

                    <p class="mb-2 ml-auto text-sm text-neutral-500">
                        {{ count($candidates) }} {{ Str::plural('question', count($candidates)) }} ready
                    </p>
                </div>

                {{-- ══ THE TABLE ══ the correct option is marked so the author
                     is checking the answer key, not just the wording. That is
                     the one thing nothing downstream can verify. --}}
                <div class="max-h-[26rem] overflow-y-auto rounded-md border border-neutral-200">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-neutral-50 text-left">
                            <tr class="border-b border-neutral-200">
                                <th class="px-3 py-2 font-medium text-neutral-600">Use</th>
                                <th class="px-3 py-2 font-medium text-neutral-600">Row</th>
                                <th class="px-3 py-2 font-medium text-neutral-600">Question &amp; options</th>
                                <th class="px-3 py-2 font-medium text-neutral-600">Type</th>
                                <th class="px-3 py-2 font-medium text-neutral-600">Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($candidates as $i => $candidate)
                                <tr class="border-b border-neutral-100 last:border-b-0 align-top" wire:key="candidate-{{ $candidate['row'] }}">
                                    <td class="px-3 py-3">
                                        <input type="checkbox" wire:model="candidates.{{ $i }}.include" class="h-4 w-4 accent-teal-600">
                                    </td>

                                    <td class="px-3 py-3 font-mono text-xs text-neutral-500">{{ $candidate['row'] }}</td>

                                    <td class="px-3 py-3">
                                        <div class="font-medium text-neutral-900">{{ $candidate['body'] }}</div>

                                        <ul class="mt-1.5 space-y-0.5">
                                            @foreach ($candidate['options'] as $option)
                                                <li class="flex items-start gap-1.5 text-xs {{ $option['is_correct'] ? 'font-semibold text-honeydew' : 'text-neutral-600' }}">
                                                    @if ($option['is_correct'])
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0" aria-hidden="true"><path d="M20 6 9 17l-5-5"></path></svg>
                                                        <span class="sr-only">Correct answer:</span>
                                                    @else
                                                        <span class="w-3 shrink-0" aria-hidden="true"></span>
                                                    @endif
                                                    {{ $option['body'] }}
                                                </li>
                                            @endforeach
                                        </ul>

                                        @if ($candidate['explanation'])
                                            <p class="mt-1.5 text-xs text-neutral-500">{{ $candidate['explanation'] }}</p>
                                        @endif
                                    </td>

                                    <td class="px-3 py-3">
                                        <select wire:model="candidates.{{ $i }}.type" class="h-8 rounded-sm border border-neutral-200 bg-white px-2 text-xs text-neutral-700">
                                            @foreach ($questionTypes as $type)
                                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                            @endforeach
                                        </select>
                                        @error('candidates.'.$i.'.type')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </td>

                                    <td class="px-3 py-3">
                                        <input type="number" step="0.5" min="0.5" wire:model="candidates.{{ $i }}.marks" class="h-8 w-20 rounded-sm border border-neutral-200 px-2 text-xs text-neutral-700">
                                        @error('candidates.'.$i.'.marks')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <x-slot:footer>
            <x-button x-on:click="$dispatch('close-modal', 'question-import')" wire:click="close" variant="secondary" size="sm">
                Cancel
            </x-button>

            @if ($candidates !== [])
                <x-button wire:click="import" wire:loading.attr="disabled" variant="primary" size="sm">
                    Import questions
                </x-button>
            @endif
        </x-slot:footer>
    </x-modal>
</div>
