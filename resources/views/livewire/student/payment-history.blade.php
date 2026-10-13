{{--
    Payment history — "you used to have this" (see the component's own
    docblock). Every order this student has ever placed, successful or not,
    styled to match My Learning rather than the admin area's dense table —
    this is a student-facing screen, not an ops dashboard.
--}}
<div class="mx-auto w-full max-w-content px-5 pb-24 pt-10 lg:px-10">
    <div class="mb-8 rounded-hero bg-gradient-to-br from-marigold via-white to-teal-50 px-6 py-8 sm:px-10">
        <p class="eyebrow text-teal-600">Your account</p>
        <h1 class="mt-2 font-serif text-[40px]/[1.08] font-normal tracking-[-0.02em] text-neutral-900 md:text-[48px]">Payment history</h1>
    </div>

    @if ($orders->isEmpty())
        <x-empty-state
            title="No purchases yet"
            description="Courses you buy will show up here, along with the receipt for each one."
        />
    @else
        <div class="divide-y divide-neutral-200 overflow-hidden rounded-[20px] border border-neutral-200 bg-white shadow-md">
            @foreach ($orders as $order)
                <div wire:key="order-{{ $order->id }}" class="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-neutral-900">{{ $order->course?->title ?? 'Course no longer available' }}</p>
                        <p class="mt-0.5 text-xs text-neutral-500">
                            {{ $order->order_number }} &middot; {{ $order->placed_at?->format('d M Y, H:i') ?? 'Not yet placed' }}
                        </p>
                    </div>

                    <div class="flex items-center gap-4">
                        <span class="font-serif text-lg font-semibold text-neutral-900">{{ $order->total }}</span>
                        <x-badge :variant="$order->status->badgeVariant()">{{ $order->status->label() }}</x-badge>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-4 text-xs text-neutral-500">
            Need a receipt or have a question about a purchase? Reply to your confirmation email and we'll help.
        </p>
    @endif
</div>
