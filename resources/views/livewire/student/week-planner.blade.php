@php
    use App\Services\Student\WeekPlan;

    $goal = $plan->goalMinutes;
    $done = $plan->doneMinutes;
    $percent = $goal > 0 ? min(100, (int) round($done / $goal * 100)) : 0;
    $presets = [120 => '2h', 300 => '5h', 480 => '8h'];
@endphp

<section aria-labelledby="week-planner-heading" data-week-planner>
    <x-card>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 id="week-planner-heading" class="font-serif text-[26px] font-medium tracking-[-0.01em]">Your week, planned</h2>
                <p class="mt-1 text-sm text-neutral-600" data-week-summary>
                    {{ WeekPlan::formatMinutes($done) }} of {{ WeekPlan::formatMinutes($goal) }} this week
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Weekly goal">
                <span class="text-sm text-neutral-500">Weekly goal</span>
                @foreach ($presets as $minutes => $label)
                    <x-button
                        type="button"
                        size="sm"
                        :variant="$goal === $minutes ? 'primary' : 'secondary'"
                        wire:click="setGoal({{ $minutes }})"
                        :aria-pressed="$goal === $minutes ? 'true' : 'false'"
                    >{{ $label }}</x-button>
                @endforeach

                <form wire:submit="setCustomGoal" class="flex items-center gap-2">
                    <label for="week-goal-custom" class="sr-only">Custom weekly goal in hours</label>
                    <input
                        id="week-goal-custom"
                        type="text"
                        inputmode="decimal"
                        wire:model="customHours"
                        placeholder="Custom (h)"
                        class="h-9 w-28 rounded-lg border border-neutral-200 bg-white px-3 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20"
                    >
                    <x-button type="submit" size="sm" variant="secondary">Set</x-button>
                </form>
            </div>
        </div>

        @error('customHours')
            <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
        @enderror

        <div class="mt-4 h-2 overflow-hidden rounded-full bg-neutral-100" role="progressbar"
             aria-label="Progress towards the weekly goal" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
            <div class="h-full rounded-full bg-teal-600" style="width: {{ $percent }}%"></div>
        </div>

        @if (! $plan->hasCourses)
            <div class="mt-6">
                <x-empty-state
                    title="Nothing to plan yet"
                    description="Enrol in a course and your week fills up with the lessons to study next."
                />
            </div>
        @else
            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
                @foreach ($plan->days as $day)
                    <div @class([
                            'min-w-0 rounded-xl border p-3',
                            'border-teal-600 bg-teal-50/60' => $day->isToday,
                            'border-neutral-200 bg-white' => ! $day->isToday,
                        ])
                        @if ($day->isToday) aria-current="date" @endif
                        data-week-day="{{ $day->date->toDateString() }}">
                        <div class="flex items-baseline justify-between">
                            <span class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $day->date->format('D') }}</span>
                            <span @class(['font-serif text-xl leading-none', 'text-teal-700' => $day->isToday])>{{ $day->date->format('j') }}</span>
                        </div>
                        @if ($day->isToday)
                            <x-badge variant="brand" class="mt-1">Today</x-badge>
                        @endif

                        <ul class="mt-3 space-y-2">
                            @forelse ($day->blocks as $block)
                                <li>
                                    <a href="{{ route('student.courses.play', [$block->course, $block->lesson]) }}"
                                       @class([
                                           'block rounded-lg px-2.5 py-2 text-xs ring-1 ring-inset transition hover:ring-teal-600',
                                           'bg-success-50 text-success-700 ring-success-600/25' => $block->done,
                                           'bg-amber-50 text-neutral-800 ring-amber-200' => ! $block->done && $block->isQuiz,
                                           'bg-neutral-50 text-neutral-800 ring-neutral-200' => ! $block->done && ! $block->isQuiz,
                                       ])>
                                        <span class="flex items-start gap-1.5">
                                            @if ($block->done)
                                                <span aria-hidden="true">&#10003;</span>
                                                <span class="sr-only">Done:</span>
                                            @endif
                                            <span class="min-w-0 break-words font-medium">
                                                @if ($block->isQuiz)<span class="mr-1 font-mono text-[10px] uppercase">Quiz</span>@endif{{ $block->title }}
                                            </span>
                                        </span>
                                        <span class="mt-0.5 block truncate text-[11px] text-neutral-500">{{ $block->course->title }}</span>
                                        <span class="mt-0.5 block text-[11px] text-neutral-500">{{ $block->minutes }} min</span>
                                    </a>
                                </li>
                            @empty
                                <li class="text-xs text-neutral-400">Nothing planned</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
            </div>

            @unless ($plan->hasAnyBlock())
                <p class="mt-4 text-sm text-neutral-600">You are all caught up. No unfinished lessons are waiting in your courses.</p>
            @endunless
        @endif
    </x-card>
</section>
