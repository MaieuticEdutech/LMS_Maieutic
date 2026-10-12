{{--
    The storefront footer, shared by the student and public layouts.

    Deliberately carries no sign-in or account links: the header already does,
    per audience, and a footer that said "Sign in" to a signed-in student would
    contradict it.
--}}
@php($branding = app(\App\Services\Settings\BrandingService::class))

<footer class="mt-20 bg-teal-900 text-teal-200">
    <div class="mx-auto grid max-w-content gap-8 px-6 py-12 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr] lg:px-9">
        <div>
            <img src="{{ asset('images/logo-maieutic-edutech.png') }}" alt="{{ $branding->organisationName() }}"
                 class="h-8 w-auto rounded-sm bg-white p-1">
            <p class="mt-3 max-w-[34ch] text-sm">Project-led courses in development, data and cloud, taught by people who work in the field.</p>
        </div>

        <div>
            <h4 class="mb-3 text-sm text-white">Learn</h4>
            <ul class="space-y-2 text-sm">
                <li><a href="{{ route('catalogue.index') }}" class="hover:text-white">Explore courses</a></li>
            </ul>
        </div>

        <div>
            <h4 class="mb-3 text-sm text-white">Help</h4>
            <p class="text-sm">Write to us at</p>
            <p class="select-all text-sm font-semibold text-white">{{ $branding->supportEmail() }}</p>
        </div>
    </div>

    <div class="border-t border-white/10">
        <p class="mx-auto max-w-content px-6 py-5 text-[12.5px] text-teal-300 lg:px-9">
            &copy; {{ now()->year }} {{ $branding->legalName() }}
        </p>
    </div>
</footer>
