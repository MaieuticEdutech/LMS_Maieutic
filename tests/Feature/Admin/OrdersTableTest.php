<?php

declare(strict_types=1);

use App\Livewire\Admin\OrdersTable;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

it('lists orders with their buyer and amount', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $course = Course::factory()->published()->pricedAt(99900)->create();
    Order::factory()->for($course)->paid()->create([
        'order_number' => 'ORD-FINDME01',
        'buyer_name' => 'Asha Rao',
        'amount_total' => 99900,
    ]);

    Livewire::test(OrdersTable::class)
        ->assertSee('ORD-FINDME01')
        ->assertSee('Asha Rao');
});

it('searches by order number, buyer name or buyer email', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Order::factory()->create(['order_number' => 'ORD-AAAAAAAAA1', 'buyer_name' => 'Asha Rao']);
    Order::factory()->create(['order_number' => 'ORD-BBBBBBBBB2', 'buyer_name' => 'Ben Cole']);

    Livewire::test(OrdersTable::class)
        ->set('search', 'Asha')
        ->assertSee('ORD-AAAAAAAAA1')
        ->assertDontSee('ORD-BBBBBBBBB2');
});

it('filters by order status', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Order::factory()->paid()->create(['order_number' => 'ORD-PAIDONE01']);
    Order::factory()->failed()->create(['order_number' => 'ORD-FAILEDON1']);

    Livewire::test(OrdersTable::class)
        ->set('statusFilter', 'paid')
        ->assertSee('ORD-PAIDONE01')
        ->assertDontSee('ORD-FAILEDON1');
});

it('shows an empty state with no orders', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(OrdersTable::class)->assertSee('No orders yet');
});

it('denies an instructor and a student from viewing the table', function (): void {
    // FR-INS-10: an instructor never sees a financial figure anywhere in
    // this system. HTTP-level, matching InstructorsTableTest's own reasoning.
    $instructor = User::factory()->instructor()->create();
    $this->actingAs($instructor)->get(route('admin.orders.index'))->assertForbidden();

    $student = User::factory()->student()->create();
    $this->actingAs($student)->get(route('admin.orders.index'))->assertForbidden();
});
