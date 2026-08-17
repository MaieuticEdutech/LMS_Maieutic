{{--
    RESOURCE LESSON PLAYER — downloadable attachments (FR-STU-09).

    A lesson whose content IS the file: worksheets, datasets, code samples,
    templates. Unlike the other types it can carry SEVERAL files, so this is
    the one player that renders a list rather than a single item.

    Every download goes through `media.url`, which authorises before issuing a
    short-lived link, and is served with Content-Disposition: attachment and
    nosniff by MediaStreamController (FR-FILE-07).

    Available: $lesson, $media (the primary file), $progress.
--}}
@php
    // All attachments on the lesson, not just the primary one. Ordered by
    // position so an author can control what a student meets first.
    $files = $lesson->media->sortBy('position');
@endphp

<div class="space-y-5">
    {{-- The summary is drawn once by the player, below the lesson title
         (design handoff §4) — see video.blade.php. --}}

    @if ($files->isNotEmpty())
        <ul class="divide-y divide-neutral-100 overflow-hidden rounded-card border border-neutral-200 bg-white">
            @foreach ($files as $file)
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-neutral-900">
                            {{ $file->original_name ?? $file->ulid }}
                        </p>
                        <p class="mt-0.5 font-mono text-xs tracking-[0.04em] text-neutral-500">
                            {{ $file->humanSize() }}
                        </p>
                    </div>

                    <x-button :href="route('media.url', $file)" variant="secondary" size="sm">
                        Download
                    </x-button>
                </li>
            @endforeach
        </ul>
    @else
        <x-empty-state
            title="No files attached"
            description="This lesson has no downloadable resources yet."
        />
    @endif

    @if ($lesson->body)
        <div class="prose-lms max-w-[68ch] leading-relaxed text-neutral-700">{!! $lesson->body !!}</div>
    @endif
</div>
