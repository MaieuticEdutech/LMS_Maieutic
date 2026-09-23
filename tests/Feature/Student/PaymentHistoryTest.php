<?php

declare(strict_types=1);

use App\Livewire\Student\PaymentHistory;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

it('shows the signed-in student their own orders', function (): void {
    $student = User::factory()->student()->create();
    $this->actingAs($student);

    $course = Course::factory()->published()->create(['title' => 'Data Structures in Depth']);
    Order::factory()->for($course)->for($student)->paid()->create(['order_number' => 'ORD-MINEONLY01']);

    Livewire::test(PaymentHistory::class)
        ->assertSee('ORD-MINEONLY01')
        ->assertSee('Data Structures in Depth');
});

it('never shows another student\'s orders', function (): void {
    $student = User::factory()->student()->create();
    $other = User::factory()->student()->create();
    $this->actingAs($student);

    Order::factory()->for($other)->paid()->create(['order_number' => 'ORD-NOTMINE001']);

    Livewire::test(PaymentHistory::class)->assertDontSee('ORD-NOTMINE001');
});

it('shows an empty state with no purchases', function (): void {
    $student = User::factory()->student()->create();
    $this->actingAs($student);

    Livewire::test(PaymentHistory::class)->assertSee('No purchases yet');
});

it('is reachable at the payment history route for a signed-in student', function (): void {
    $student = User::factory()->student()->create();

    $this->actingAs($student)
        ->get(route('student.payments.index'))
        ->assertOk()
        ->assertSee('Payment history');
});
