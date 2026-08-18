{{--
    PRESENTATION LESSON PLAYER — PPT/PPTX/ODP (FR-STU-09).

    Deliberately download-only, not embedded. No browser renders PPTX natively,
    and the alternatives are a third-party viewer service — which would mean
    shipping protected course content to somebody else's server — or a
    conversion pipeline nobody has asked for.

    So the honest thing is a clear download, with the file's size and type
    stated up front so a student on a phone knows what they are about to pull
    down before they tap it.

    Available: $lesson, $media (MediaFile|null), $progress.
--}}
<div class="space-y-5">
    @if ($media)
        <div class="rounded-card border border-neutral-200 bg-white p-6">
            <p class="eyebrow text-teal-600">Presentation</p>

            <h2 class="mt-3 text-xl">{{ $media->original_name ?? $lesson->title }}</h2>

            <p class="mt-2 text-sm text-neutral-500">
                {{ $media->humanSize() }}
                @if ($media->mime_type)
                    &middot; {{ $media->mime_type }}
                @endif
            </p>

            <div class="mt-5">
                <x-button :href="route('media.url', $media)">
                    Download presentation
                </x-button>
            </div>

            <p class="mt-4 max-w-[60ch] text-xs leading-relaxed text-neutral-500">
                Slides open in PowerPoint, Keynote, Google Slides or LibreOffice.
                They are not shown in the browser.
            </p>
        </div>
    @else
        <x-empty-state
            title="No slides attached"
            description="This lesson has no presentation file yet. It will appear here once one is uploaded."
        />
    @endif

    {{-- The summary is drawn once by the player, below the lesson title
         (design handoff §4) — see video.blade.php. --}}

    @if ($lesson->body)
        <div class="prose-lms max-w-[68ch] leading-relaxed text-neutral-700">{!! $lesson->body !!}</div>
    @endif
</div>
