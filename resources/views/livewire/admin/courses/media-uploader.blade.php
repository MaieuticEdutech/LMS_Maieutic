{{--
    Upload widget for a course thumbnail, a lesson's primary asset and a
    lesson's attachments.

    ═════════════════════════════════════════════════════════════════════════
    THE PROGRESS BAR REPORTS REAL BYTES, AND AN UPLOAD HAS TWO PHASES.

    It used to be `width: 60%` with a pulse animation — a fixed bar describing
    nothing. A 20 KB PDF and a 2 GB lecture rendered identically and neither
    ever moved, so "is this working or has it hung?" was unanswerable. That is
    the one question the control exists to answer.

    PHASE 1 — the browser sends the file. Livewire emits
    `livewire-upload-progress` with a real percentage, so the bar follows the
    bytes and the file's own name and size are shown beside it.

    PHASE 2 — the server validates, sniffs the content type and writes to
    storage. With the content disk on Backblaze that is a SECOND upload, off
    this machine, which can take as long again. A bar sitting at 100%
    throughout reads as a hang, so this phase says "Processing" in its own
    right. It is driven by wire:loading rather than by hand: Livewire knows
    exactly when its own round trip ends, and anything we tracked ourselves
    would drift out of step with it.

    FAILURES ARE SHOWN, NOT SWALLOWED. `livewire-upload-error` fires when the
    browser could not deliver the file at all — a dropped connection, a proxy
    limit, a refused body. Nothing listened for it, so the indicator simply
    vanished and the file was silently absent. Server-side rejections (wrong
    type, too large, failed content sniff) still arrive through @error('file').
    ═════════════════════════════════════════════════════════════════════════

    ═════════════════════════════════════════════════════════════════════════
    THERE IS A CANCEL BUTTON AND DELIBERATELY NO PAUSE BUTTON.

    Cancel is real: `$wire.cancelUpload()` aborts the request in flight, and
    nothing partial survives — the server only assembles a temporary file once
    an upload completes.

    Pause is not offered because this upload mechanism cannot do it. The file
    goes up as ONE HTTP request, and an in-flight request can be aborted but
    not suspended: there is no way to tell the connection "hold here" and pick
    up the same byte later. Anything labelled "pause" over this transport
    would abort and restart from zero on resume — which on a 478 MB file over
    a slow link is the most expensive possible lie to tell someone.

    Real pause/resume needs a RESUMABLE protocol, where the file is cut into
    parts the browser uploads individually and can re-request by index — S3/B2
    multipart with client-side part tracking, or tus. That is the same change
    as moving uploads direct to Backblaze, and it belongs with that decision
    rather than being faked here.
    ═════════════════════════════════════════════════════════════════════════
--}}
@php $field = $multiple ? 'files' : 'file'; @endphp

<div
    x-data="{
        uploading: false,
        progress: 0,
        name: '',
        size: '',
        failed: false,
        cancelled: false,

        /** Read the chosen file's real name and size straight off the input. */
        note(input) {
            const file = input.files?.[0]

            if (! file) return

            this.name = file.name
            this.size = this.humanSize(file.size)
            this.failed = false
            this.cancelled = false
        },

        humanSize(bytes) {
            if (! bytes) return ''

            const units = ['B', 'KB', 'MB', 'GB']
            let i = 0

            while (bytes >= 1024 && i < units.length - 1) {
                bytes /= 1024
                i++
            }

            return bytes.toFixed(i === 0 ? 0 : 1) + ' ' + units[i]
        },
    }"
    x-on:livewire-upload-start="failed = false; cancelled = false; uploading = true; progress = 0"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:livewire-upload-finish="uploading = false; progress = 100"
    x-on:livewire-upload-cancel="uploading = false; progress = 0; cancelled = true"
    x-on:livewire-upload-error="uploading = false; progress = 0; failed = true"
>
    @forelse ($media as $file)
        <div wire:key="media-{{ $file->id }}" class="mb-2 flex items-center gap-3 rounded-control border border-neutral-200 bg-neutral-50 px-3.5 py-2.5">
            {{--
                A thumbnail is SHOWN, not described.

                Everything else here is a generic file row, which is right for
                a video or a PDF — there is nothing to look at, and those live
                on the private disk with no plain URL anyway. An image is
                different: the author picked it for how it looks, and a row
                reading "cover.png · 240 KB" beside a paper icon proves only
                that a file arrived.

                It also makes a broken pipeline visible. A missing storage
                symlink leaves the record perfect, the bytes on disk, and every
                URL 404 — which showed up as "I uploaded a thumbnail and
                nothing changed" and could not be diagnosed from this screen,
                because this screen never tried to load the image.
            --}}
            @php($preview = $file->previewUrl())

            @if ($preview)
                <img src="{{ $preview }}" alt="{{ $file->original_name }}"
                     class="h-10 w-16 shrink-0 rounded-sm border border-neutral-200 bg-white object-cover">
            @else
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-teal-600" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"></path><path d="M14 2v5h5"></path></svg>
            @endif
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold text-neutral-900">{{ $file->original_name }}</div>
                <div class="text-xs text-neutral-500">{{ $file->humanSize() }}</div>
            </div>
            <label class="cursor-pointer rounded-sm border border-neutral-300 bg-white px-2.5 py-1 text-xs font-semibold text-neutral-700 hover:bg-neutral-100">
                Replace
                <input type="file" wire:model="{{ $field }}" x-on:change="note($event.target)" class="sr-only">
            </label>
            <button type="button" wire:click="confirmRemove({{ $file->id }})" x-on:click="$dispatch('open-modal', 'remove-media-{{ $file->id }}')" class="flex h-7 w-7 items-center justify-center rounded-sm border border-neutral-200 text-neutral-500 hover:border-red-200 hover:text-red-600" aria-label="Remove file">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </button>

            <x-modal name="remove-media-{{ $file->id }}" title="Remove file">
                <p class="text-sm text-neutral-600">Remove <span class="font-medium">{{ $file->original_name }}</span>?</p>
                <x-slot:footer>
                    <x-button x-on:click="$dispatch('close-modal', 'remove-media-{{ $file->id }}')" variant="secondary" size="sm">Cancel</x-button>
                    <x-button wire:click="remove" x-on:click="$dispatch('close-modal', 'remove-media-{{ $file->id }}')" variant="danger" size="sm">Remove</x-button>
                </x-slot:footer>
            </x-modal>
        </div>
    @empty
        <label class="block cursor-pointer rounded-control border-2 border-dashed border-neutral-300 px-5 py-6 text-center hover:border-teal-400 hover:bg-teal-50">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1.5 text-neutral-400" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
            <span class="block text-sm font-semibold text-neutral-900">Drop a file or browse</span>
            <span class="block text-xs text-neutral-500">Up to the configured size limit for this content type</span>
            <input type="file" wire:model="{{ $field }}" x-on:change="note($event.target)" @if ($multiple) multiple @endif class="sr-only">
        </label>
    @endforelse

    {{-- ══ PHASE 1 — the browser is sending ══ --}}
    <div x-show="uploading" x-cloak class="mt-2 rounded-control border border-neutral-200 bg-neutral-50 px-3.5 py-2.5">
        <div class="mb-1.5 flex items-baseline justify-between gap-3">
            <p class="min-w-0 truncate text-xs font-medium text-neutral-800" x-text="name || 'Uploading'"></p>
            <p class="shrink-0 font-mono text-[11px] tracking-[0.04em] text-neutral-500">
                <span x-text="progress + '%'"></span>
            </p>
        </div>

        <div class="h-1.5 overflow-hidden rounded-full bg-neutral-200"
             role="progressbar" aria-valuemin="0" aria-valuemax="100"
             :aria-valuenow="progress" :aria-label="'Uploading ' + (name || 'file')">
            {{-- Width is the real figure. No animation: a moving bar that is
                 not tied to bytes is a lie told smoothly. --}}
            <div class="h-full rounded-full bg-teal-600 transition-[width] duration-200" :style="`width: ${progress}%`"></div>
        </div>

        <div class="mt-1.5 flex items-center justify-between gap-3">
            <p class="text-xs text-neutral-500">
                <span x-text="size"></span><span x-show="size"> &middot; </span>sending to the server
            </p>

            {{--
                Cancel aborts the request in flight. Worth having on its own
                terms: a 478 MB file sent by mistake is otherwise four minutes
                of watching a bar you cannot stop.

                `$wire.cancelUpload` is Livewire's own API — it aborts the
                XHR and fires livewire-upload-cancel, which resets the state
                below. Nothing partial is left behind: the server only ever
                assembles a temporary file once the upload completes.

                There is deliberately NO pause button. See the note at the top
                of this file — a single XHR cannot be paused, only aborted,
                and a "pause" that silently restarted from zero would be worse
                than not offering one.
            --}}
            <button type="button"
                    x-on:click="$wire.cancelUpload('{{ $field }}')"
                    class="shrink-0 text-xs font-medium text-neutral-500 underline-offset-4 transition-colors hover:text-red-600 hover:underline">
                Cancel upload
            </button>
        </div>
    </div>

    {{-- ══ CANCELLED ══ --}}
    <div x-show="cancelled" x-cloak class="mt-2 rounded-control bg-neutral-100 px-3.5 py-2.5">
        <p class="text-xs text-neutral-600">
            Upload cancelled. Nothing was saved &mdash; choose a file to start again.
        </p>
    </div>

    {{-- ══ PHASE 2 — the server is storing it ══
         Only once the browser has finished, so the two never show together. --}}
    <div wire:loading wire:target="{{ $field }}" x-show="! uploading" x-cloak
         class="mt-2 rounded-control border border-neutral-200 bg-neutral-50 px-3.5 py-2.5">
        <div class="mb-1.5 h-1.5 overflow-hidden rounded-full bg-neutral-200">
            <div class="h-full w-full animate-pulse rounded-full bg-teal-600/70"></div>
        </div>
        <p class="text-xs text-neutral-500">
            Processing and saving to storage&hellip; large videos can take a while.
        </p>
    </div>

    {{-- ══ THE BROWSER COULD NOT DELIVER IT ══ --}}
    <div x-show="failed" x-cloak class="mt-2 rounded-control bg-red-50 px-3.5 py-2.5 ring-1 ring-inset ring-red-200">
        <p class="text-xs font-semibold text-red-600">Upload failed before it reached the server.</p>
        <p class="mt-1 text-xs text-red-600/90">
            The connection dropped, or the file was refused in transit — often because it is larger
            than the server accepts. Check the file size and try again.
        </p>
    </div>

    {{-- ══ THE SERVER REJECTED IT ══
         Wrong type, over the configured ceiling, or a failed content sniff.
         MediaValidationException's message is written to be read by a human,
         so it is shown as-is rather than replaced with something generic. --}}
    @error('file')
        <div class="mt-2 rounded-control bg-red-50 px-3.5 py-2.5 ring-1 ring-inset ring-red-200">
            <p class="text-xs font-semibold text-red-600">This file was not accepted.</p>
            <p class="mt-1 text-xs text-red-600/90">{{ $message }}</p>
        </div>
    @enderror

    @error('files.*')
        <div class="mt-2 rounded-control bg-red-50 px-3.5 py-2.5 ring-1 ring-inset ring-red-200">
            <p class="text-xs font-semibold text-red-600">This file was not accepted.</p>
            <p class="mt-1 text-xs text-red-600/90">{{ $message }}</p>
        </div>
    @enderror
</div>
