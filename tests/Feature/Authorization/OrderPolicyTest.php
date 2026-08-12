<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| OrderPolicy — FR-INS-10, architecture.md §8.3
|--------------------------------------------------------------------------
|
| "An instructor never sees a financial figure anywhere in this system."
| Unconditional — checked even when the instructor is also the buyer.
|
*/

it('lets a super admin view any order', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $order = Order::factory()->create();

    expect($admin->can('viewAny', Order::class))->toBeTrue()
        ->and($admin->can('view', $order))->toBeTrue();
});

it('lets a student view only their own order', function (): void {
    $student = User::factory()->student()->create();
    $own = Order::factory()->create(['user_id' => $student->id]);
    $someoneElses = Order::factory()->create();

    expect($student->can('viewAny', Order::class))->toBeFalse()
        ->and($student->can('view', $own))->toBeTrue()
        ->and($student->can('view', $someoneElses))->toBeFalse();
});

it('denies an instructor unconditionally, even for an order they placed themselves', function (): void {
    $instructor = User::factory()->instructor()->create();
    $ownOrder = Order::factory()->create(['user_id' => $instructor->id]);
    $otherOrder = Order::factory()->create();

    expect($instructor->can('viewAny', Order::class))->toBeFalse()
        ->and($instructor->can('view', $ownOrder))->toBeFalse()
        ->and($instructor->can('view', $otherOrder))->toBeFalse();
});

it('denies a student viewing an order with no buyer at all', function (): void {
    $student = User::factory()->student()->create();
    $guestOrder = Order::factory()->forGuestBuyer()->create();

    expect($student->can('view', $guestOrder))->toBeFalse();
});
