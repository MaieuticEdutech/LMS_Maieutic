<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| PaymentPolicy — FR-INS-10, architecture.md §8.3
|--------------------------------------------------------------------------
|
| Same shape and same rule as OrderPolicy: "An instructor never sees a
| financial figure anywhere in this system." Unconditional.
|
*/

it('lets a super admin view any payment', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $payment = Payment::factory()->create();

    expect($admin->can('viewAny', Payment::class))->toBeTrue()
        ->and($admin->can('view', $payment))->toBeTrue();
});

it('lets a student view only a payment on their own order', function (): void {
    $student = User::factory()->student()->create();
    $ownOrder = Order::factory()->create(['user_id' => $student->id]);
    $ownPayment = Payment::factory()->create(['order_id' => $ownOrder->id]);
    $someoneElsesPayment = Payment::factory()->create();

    expect($student->can('viewAny', Payment::class))->toBeFalse()
        ->and($student->can('view', $ownPayment))->toBeTrue()
        ->and($student->can('view', $someoneElsesPayment))->toBeFalse();
});

it('denies an instructor unconditionally, even for a payment on their own order', function (): void {
    $instructor = User::factory()->instructor()->create();
    $ownOrder = Order::factory()->create(['user_id' => $instructor->id]);
    $ownPayment = Payment::factory()->create(['order_id' => $ownOrder->id]);
    $otherPayment = Payment::factory()->create();

    expect($instructor->can('viewAny', Payment::class))->toBeFalse()
        ->and($instructor->can('view', $ownPayment))->toBeFalse()
        ->and($instructor->can('view', $otherPayment))->toBeFalse();
});

it('denies a student viewing a payment on a guest (no-buyer) order', function (): void {
    $student = User::factory()->student()->create();
    $guestOrder = Order::factory()->forGuestBuyer()->create();
    $payment = Payment::factory()->create(['order_id' => $guestOrder->id]);

    expect($student->can('view', $payment))->toBeFalse();
});
