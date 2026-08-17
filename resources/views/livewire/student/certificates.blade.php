{{--
    Certificates (design handoff §7).

    Eyebrow, serif title, count line, then a two-up grid of cards: a dark banner
    carrying the reversed logo and the course title, over a white footer with the
    completion date, the mono certificate number, and View / Download.

    Every string on a card comes from the certificate's own SNAPSHOT columns —
    never from the course or the user. A course renamed next year must not
    rewrite a document an employer verified this year.
--}}
<div class="mx-auto w-full max-w-content px-5 pb-24 pt-12 lg:px-10">

    <p class="eyebrow text-teal-600">Achievements</p>

    <h1 class="mt-2.5 font-serif text-[40px]/[1.1] font-medium">Certificates</h1>

    @if ($certificates->isEmpty())
        <div class="mt-10">
            <x-empty-state
                title="No certificates yet"
                description="Finish a course and its certificate appears here, with an ID anyone can verify."
            >
                <x-slot:action>
                    <x-button :href="route('student.courses.index')">Back to My Learning</x-button>
                </x-slot:action>
            </x-empty-state>
        </div>
    @else
        <p class="mb-10 mt-2 text-base/[1.6] text-neutral-500">
            {{ $certificates->count() }} {{ Str::plural('certificate', $certificates->count()) }} earned.
            Each one is verifiable by its ID.
        </p>

        <div class="grid gap-5 lg:grid-cols-2">
            @foreach ($certificates as $certificate)
                <div class="overflow-hidden rounded-card border border-neutral-200 bg-white"
                     wire:key="certificate-{{ $certificate->id }}">

                    {{-- The handoff's banner gradient: deep teal with a red
                         wedge at 78%, echoing the M motif. --}}
                    <div class="p-7 text-white"
                         style="background:linear-gradient(118deg,#052826 0%,#052826 78%,#800d07 78%)">

                        <img src="{{ asset('images/logo-maieutic-reversed.png') }}"
                             alt=""
                             class="mb-[18px] h-[22px] w-auto opacity-95">

                        <div class="mb-2 font-mono text-[10px] font-semibold tracking-[0.16em] text-teal-300">
                            CERTIFICATE OF COMPLETION
                        </div>

                        <div class="font-serif text-[22px] font-medium">{{ $certificate->course_title }}</div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-[18px]">
                        <div>
                            <div class="text-[13px] text-neutral-500">
                                Completed {{ $certificate->issued_at->format('j F Y') }}
                            </div>
                            <div class="mt-[3px] font-mono text-xs text-neutral-400">{{ $certificate->number }}</div>
                        </div>

                        <div class="flex gap-2">
                            <a href="{{ route('certificates.verify', $certificate) }}"
                               class="rounded-sm border border-neutral-300 bg-white px-3.5 py-[9px] text-[13px] font-semibold text-neutral-700 transition-colors hover:bg-neutral-50">
                                View
                            </a>

                            {{-- "Download" opens the certificate and asks the
                                 browser to print it. NOT a server-generated PDF:
                                 that needs a new Composer dependency, and
                                 composer.json belongs to another track — a new
                                 package there needs a recorded justification
                                 rather than being slipped in with a UI change.
                                 Print-to-PDF is built into every browser and
                                 produces the same document. --}}
                            <a href="{{ route('certificates.verify', $certificate) }}?print=1"
                               class="rounded-sm bg-teal-600 px-3.5 py-[9px] text-[13px] font-semibold text-white transition-colors hover:bg-teal-700">
                                Download
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
