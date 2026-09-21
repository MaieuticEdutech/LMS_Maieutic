<div class="mx-auto max-w-lg px-6 py-16 text-center" @if ($stillWaiting) wire:poll.2s @endif>
    <div class="rounded-lg border border-neutral-200 bg-neutral-0 p-10 shadow-sm">
        @if ($stillWaiting)
            <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-teal-200 border-t-teal-600"></div>
            <h1 class="mt-6 font-serif text-xl font-semibold text-neutral-900">Payment received — activating access</h1>
            <p class="mt-2 text-sm text-neutral-500">This usually takes a few seconds. Please don't close this page.</p>

        @elseif ($order->status === \App\Enums\OrderStatus::Paid)
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h1 class="mt-6 font-serif text-xl font-semibold text-neutral-900">You're in</h1>
            <p class="mt-2 text-sm text-neutral-500">Payment confirmed for <strong>{{ $order->course->title }}</strong>.</p>

            @if ($viewerIsBuyer)
                <x-button :href="route('student.courses.index')" variant="primary" size="lg" class="mt-6 w-full" wire:navigate>
                    Go to my courses
                </x-button>
            @else
                <p class="mt-6 rounded-sm bg-teal-50 px-4 py-3 text-sm text-teal-800">
                    We've sent an email to <strong>{{ $order->buyer_email }}</strong> with a link to set your password and start learning.
                </p>
                <x-button :href="route('login')" variant="secondary" size="lg" class="mt-4 w-full" wire:navigate>
                    Sign in
                </x-button>
            @endif

        @else
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>
            <h1 class="mt-6 font-serif text-xl font-semibold text-neutral-900">Payment didn't go through</h1>
            <p class="mt-2 text-sm text-neutral-500">No amount was charged. You can try again whenever you're ready.</p>

            <x-button :href="route('checkout.start', ['course' => $order->course])" variant="primary" size="lg" class="mt-6 w-full" wire:navigate>
                Try again
            </x-button>
        @endif

        <p class="mt-6 text-xs text-neutral-400">Order {{ $order->order_number }}</p>
    </div>
</div>
