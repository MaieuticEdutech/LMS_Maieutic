<div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-stat-tile label="Students" :value="$studentCount" />
        <x-stat-tile label="Instructors" :value="$instructorCount" />
        <x-stat-tile label="Published courses" :value="$publishedCourseCount" />
        <x-stat-tile label="Active enrollments" :value="$activeEnrollmentCount" />
        <x-stat-tile label="Revenue" :value="(string) $totalRevenue" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card title="Recent orders">
            @if ($recentOrders->isEmpty())
                <x-empty-state title="No orders yet" description="Orders appear here once the first purchase completes." />
            @else
                <x-table>
                    <x-slot:head>
                        <th class="px-3 py-2">Buyer</th>
                        <th class="px-3 py-2">Course</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Placed</th>
                    </x-slot:head>

                    @foreach ($recentOrders as $order)
                        <tr>
                            <td class="px-3 py-2">{{ $order->buyer_name }}</td>
                            <td class="px-3 py-2 text-zinc-500">{{ $order->course?->title }}</td>
                            <td class="px-3 py-2"><x-badge :variant="$order->status->badgeVariant()">{{ $order->status->label() }}</x-badge></td>
                            <td class="px-3 py-2 text-zinc-500">{{ $order->created_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>

        <x-card title="Recent enrollments">
            @if ($recentEnrollments->isEmpty())
                <x-empty-state title="No enrollments yet" description="Enrollments appear here once a student is granted course access." />
            @else
                <x-table>
                    <x-slot:head>
                        <th class="px-3 py-2">Student</th>
                        <th class="px-3 py-2">Course</th>
                        <th class="px-3 py-2">Enrolled</th>
                    </x-slot:head>

                    @foreach ($recentEnrollments as $enrollment)
                        <tr>
                            <td class="px-3 py-2">{{ $enrollment->user?->name }}</td>
                            <td class="px-3 py-2 text-zinc-500">{{ $enrollment->course?->title }}</td>
                            <td class="px-3 py-2 text-zinc-500">{{ $enrollment->created_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    </div>

    @if ($recentFailedWebhooks->isNotEmpty())
        <div class="mt-6">
            <x-card>
                <x-slot:header>
                    <h2 class="text-sm font-semibold text-danger-700">Failed webhooks — needs attention</h2>
                </x-slot:header>

                <x-table>
                    <x-slot:head>
                        <th class="px-3 py-2">Gateway</th>
                        <th class="px-3 py-2">Event</th>
                        <th class="px-3 py-2">Error</th>
                        <th class="px-3 py-2">Received</th>
                    </x-slot:head>

                    @foreach ($recentFailedWebhooks as $webhookEvent)
                        <tr>
                            <td class="px-3 py-2">{{ $webhookEvent->gateway }}</td>
                            <td class="px-3 py-2 text-zinc-500">{{ $webhookEvent->event_type }}</td>
                            <td class="px-3 py-2 text-zinc-500">{{ str($webhookEvent->last_error ?? '')->limit(60) }}</td>
                            <td class="px-3 py-2 text-zinc-500">{{ $webhookEvent->received_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        </div>
    @endif
</div>
