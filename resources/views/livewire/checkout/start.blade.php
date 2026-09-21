<div
    x-data="checkoutForm()"
    x-on:checkout:open.window="open($event.detail[0])"
    class="mx-auto max-w-lg px-6 py-12"
>
    <div class="rounded-lg border border-neutral-200 bg-neutral-0 p-8 shadow-sm">
        <p class="text-sm font-medium text-teal-700">Checkout</p>
        <h1 class="mt-1 font-serif text-2xl font-semibold text-neutral-900">{{ $course->title }}</h1>
        <p class="mt-2 text-3xl font-semibold text-neutral-900">{{ $course->price }}</p>

        @error('course')
            <p class="mt-4 rounded-sm bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
        @enderror

        <form wire:submit="submit" class="mt-6 space-y-5">
            <x-input label="Full name" name="name" wire:model="name" required autofocus />
            <x-input label="Email address" name="email" type="email" wire:model="email" required />
            <x-input label="Phone number" name="phone" type="tel" wire:model="phone" />

            <x-button type="submit" variant="primary" size="lg" class="w-full" wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">Pay {{ $course->price }}</span>
                <span wire:loading wire:target="submit">Starting checkout…</span>
            </x-button>

            <p class="text-center text-xs text-neutral-500">
                You'll be asked to pay on Razorpay's secure checkout. Nothing is charged until you complete payment there.
            </p>
        </form>
    </div>
</div>

{{--
    Razorpay's hosted checkout is a client-side modal — this is the one place
    in the application that talks to a third-party script directly, rather
    than through routes/media.php's server-issued URLs. The modal's own
    success callback is deliberately NOT used to grant anything; it only
    redirects to the status page, which polls the database for what the
    webhook actually settled (architecture.md §11.2, "client callback
    INFORMATIONAL ONLY").

    Phase 14 (security headers) must allowlist https://checkout.razorpay.com
    in the CSP script-src when it lands — this tag is exactly what that
    policy needs to permit.
--}}
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    function checkoutForm() {
        return {
            open(detail) {
                const options = {
                    key: detail.keyId,
                    order_id: detail.gatewayOrderId,
                    amount: detail.amount,
                    currency: detail.currency,
                    name: detail.courseTitle,
                    description: 'Course purchase',
                    prefill: {
                        name: detail.name,
                        email: detail.email,
                        contact: detail.contact ?? '',
                    },
                    handler: () => {
                        window.location.href = detail.statusUrl;
                    },
                    modal: {
                        // The buyer closing the modal without paying is not a
                        // failure to record — the order simply stays `pending`
                        // and either a retry or the reconciliation sweep
                        // resolves it later. No client-side write happens here
                        // either way.
                        ondismiss: () => {},
                    },
                };

                new Razorpay(options).open();
            },
        };
    }
</script>
