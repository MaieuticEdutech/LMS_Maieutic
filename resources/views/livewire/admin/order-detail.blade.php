<div class="space-y-6">
    <div class="rounded-card border border-neutral-200 bg-white p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="eyebrow text-neutral-500">Order</p>
                <h2 class="mt-1 font-serif text-2xl font-semibold text-neutral-900">{{ $order->order_number }}</h2>
            </div>
            <x-badge :variant="$order->status->badgeVariant()">{{ $order->status->label() }}</x-badge>
        </div>

        <dl class="mt-6 grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="eyebrow text-neutral-500">Buyer</dt>
                <dd class="mt-1 text-sm text-neutral-900">{{ $order->buyer_name }}</dd>
                <dd class="text-xs text-neutral-500">{{ $order->buyer_email }}</dd>
                @if ($order->buyer_phone)
                    <dd class="text-xs text-neutral-500">{{ $order->buyer_phone }}</dd>
                @endif
            </div>

            <div>
                <dt class="eyebrow text-neutral-500">Account</dt>
                @if ($order->user)
                    <dd class="mt-1 text-sm text-neutral-900">{{ $order->user->name }}</dd>
                    <dd class="text-xs text-neutral-500">{{ $order->user->email }}</dd>
                @else
                    <dd class="mt-1 text-sm text-neutral-400">Not yet resolved — settles on a verified payment</dd>
                @endif
            </div>

            <div>
                <dt class="eyebrow text-neutral-500">Course</dt>
                <dd class="mt-1 text-sm text-neutral-900">{{ $order->course?->title ?? '—' }}</dd>
            </div>

            <div>
                <dt class="eyebrow text-neutral-500">Subtotal</dt>
                <dd class="mt-1 font-serif text-lg text-neutral-900">{{ $order->subtotal }}</dd>
            </div>

            @if ($order->discount_amount > 0)
                <div>
                    <dt class="eyebrow text-neutral-500">Discount</dt>
                    <dd class="mt-1 font-serif text-lg text-neutral-900">&minus;{{ $order->discount }}</dd>
                </div>
            @endif

            <div>
                <dt class="eyebrow text-neutral-500">Total</dt>
                <dd class="mt-1 font-serif text-lg font-semibold text-neutral-900">{{ $order->total }}</dd>
            </div>

            <div>
                <dt class="eyebrow text-neutral-500">Placed</dt>
                <dd class="mt-1 text-sm text-neutral-900">{{ $order->placed_at?->format('d M Y, H:i') ?? '—' }}</dd>
            </div>

            <div>
                <dt class="eyebrow text-neutral-500">Paid</dt>
                <dd class="mt-1 text-sm text-neutral-900">{{ $order->paid_at?->format('d M Y, H:i') ?? '—' }}</dd>
            </div>

            <div>
                <dt class="eyebrow text-neutral-500">Gateway order id</dt>
                <dd class="mt-1 font-mono text-xs text-neutral-600">{{ $order->gateway_order_id ?? '—' }}</dd>
            </div>
        </dl>

        @if ($order->failed_reason)
            <div class="mt-6 rounded-sm border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $order->failed_reason }}
            </div>
        @endif
    </div>

    <div>
        <h3 class="mb-3 font-serif text-lg font-semibold text-neutral-900">Payment attempts</h3>

        @if ($order->payments->isEmpty())
            <x-empty-state
                title="No payment attempts yet"
                description="A payment attempt appears here the moment the gateway reports one — captured or failed."
            />
        @else
            <x-table caption="Payment attempts for {{ $order->order_number }}">
                <x-slot:head>
                    <th class="px-3 py-2">Gateway payment id</th>
                    <th class="px-3 py-2">Method</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Captured</th>
                    <th class="px-3 py-2">Failure</th>
                </x-slot:head>

                @foreach ($order->payments as $payment)
                    <tr wire:key="payment-{{ $payment->id }}">
                        <td class="px-3 py-2 font-mono text-xs text-neutral-600">{{ $payment->gateway_payment_id }}</td>
                        <td class="px-3 py-2 text-neutral-500">{{ $payment->method ?? '—' }}</td>
                        <td class="whitespace-nowrap px-3 py-2 font-medium text-neutral-900">{{ $payment->money }}</td>
                        <td class="px-3 py-2">
                            @if ($payment->status === \App\Enums\PaymentStatus::Captured)
                                <x-badge variant="success">Captured</x-badge>
                            @elseif ($payment->status === \App\Enums\PaymentStatus::Failed)
                                <x-badge variant="danger">Failed</x-badge>
                            @elseif ($payment->status === \App\Enums\PaymentStatus::Refunded)
                                <x-badge variant="warning">Refunded</x-badge>
                            @else
                                <x-badge variant="neutral">{{ ucfirst($payment->status->value) }}</x-badge>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 text-neutral-500">{{ $payment->captured_at?->format('d M Y, H:i') ?? '—' }}</td>
                        <td class="px-3 py-2 text-neutral-500">
                            @if ($payment->failure_reason)
                                <span title="{{ $payment->failure_code }}">{{ $payment->failure_reason }}</span>
                            @else
                                &mdash;
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</div>
