<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

it('shows the order and its payment attempts', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->published()->pricedAt(149900)->create(['title' => 'Machine Learning Fundamentals']);
    $order = Order::factory()->for($course)->paid()->create([
        'order_number' => 'ORD-DETAILVIEW',
        'buyer_name' => 'Asha Rao',
        'amount_total' => 149900,
    ]);

    $payment = Payment::factory()->for($order)->create(['gateway_payment_id' => 'pay_lookup123']);

    $this->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('ORD-DETAILVIEW')
        ->assertSee('Asha Rao')
        ->assertSee('Machine Learning Fundamentals')
        ->assertSee('pay_lookup123');
});

it('shows an empty state when an order has no payment attempts', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $order = Order::factory()->create();

    $this->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('No payment attempts yet');
});

it('denies an instructor and a student from viewing an order', function (): void {
    $order = Order::factory()->create();

    $instructor = User::factory()->instructor()->create();
    $this->actingAs($instructor)->get(route('admin.orders.show', $order))->assertForbidden();

    // A student who did NOT place this order — OrderPolicy::view refuses
    // anyone but the buyer or a super admin.
    $stranger = User::factory()->student()->create();
    $this->actingAs($stranger)->get(route('admin.orders.show', $order))->assertForbidden();
});
