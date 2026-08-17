{{--
    DOCUMENT LESSON PLAYER — PDF (FR-STU-09, FR-FILE-07).

    Viewed inline where the browser can, downloaded where it cannot.

    NO STORAGE PATH APPEARS IN THIS MARKUP. The iframe src is fetched from
    `media.url` after the policy runs, exactly as the video player does.
    Rendering the path directly would put a permanent unauthorised link in the
    page source (architecture.md §16.2).

    Available: $lesson, $media (MediaFile|null), $progress.
--}}
<div
    x-data="lessonDocument({ urlEndpoint: @js($media ? route('media.url', $media) : null) })"
    x-init="load()"
    class="space-y-4"
>
    @if ($media)
        <div class="overflow-hidden rounded-card border border-neutral-200 bg-neutral-100">
            {{-- Height rather than aspect-ratio: a PDF page is portrait, and an
                 aspect-video frame would show a sliver of it. --}}
            <iframe
                x-ref="frame"
                class="h-[70vh] min-h-100 w-full"
                title="{{ $lesson->title }}"
                x-show="! failed"
            ></iframe>

            <div x-show="failed" x-cloak class="p-8 text-center">
                <p class="text-sm text-neutral-600">This document link expired.</p>
                <button type="button" x-on:click="load()"
                        class="eyebrow mt-3 text-teal-600 underline underline-offset-4">
                    Reload document
                </button>
            </div>
        </div>

        @if ($media->is_downloadable)
            {{-- Downloadable is a per-file decision the admin made, enforced by
                 MediaFilePolicy::download(). Hiding this button would not be
                 the control; the policy is (Rule 20). --}}
            <div class="flex items-center gap-3">
                <x-button :href="route('media.url', $media)" variant="secondary" size="sm">
                    Download {{ $media->original_name ?? 'file' }}
                </x-button>
                <span class="text-xs text-neutral-500">{{ $media->humanSize() }}</span>
            </div>
        @endif
    @else
        <x-empty-state
            title="No document attached"
            description="This lesson has no file yet. It will appear here once one is uploaded."
        />
    @endif

    {{-- The summary is drawn once by the player, below the lesson title
         (design handoff §4) — see video.blade.php. --}}
</div>
