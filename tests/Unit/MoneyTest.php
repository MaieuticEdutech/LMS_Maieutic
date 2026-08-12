<?php

declare(strict_types=1);

use App\Support\Money;

/*
|--------------------------------------------------------------------------
| Money — ADR-007
|--------------------------------------------------------------------------
|
| Money is the one piece of Phase 1 logic that carries real risk, so it is
| tested properly now rather than "when it's used". Every price, order total
| and payment amount in the system flows through this class; an arithmetic or
| rounding defect here becomes a reconciliation problem in Phase 12.
|
| Pure logic: no container, no database (planning.md §12.1).
|
*/

it('stores minor units exactly', function (): void {
    $money = Money::fromMinor(149900);

    expect($money->amount)->toBe(149900)
        ->and($money->currency)->toBe('INR')
        ->and($money->toMajor())->toBe(1499.0);
});

it('converts major units to minor units without float drift', function (): void {
    // The canonical float trap: 0.1 + 0.2 !== 0.3 in binary floating point.
    // Money must not inherit that.
    expect(Money::fromMajor(0.1)->add(Money::fromMajor(0.2))->amount)
        ->toBe(Money::fromMajor(0.3)->amount);

    expect(Money::fromMajor(1499.99)->amount)->toBe(149999);
    expect(Money::fromMajor('1499.99')->amount)->toBe(149999);
});

it('rounds to the nearest minor unit rather than truncating', function (): void {
    // 199.999 rupees is 20000 paise, not 19999 — truncation would quietly
    // under-charge on every such price.
    expect(Money::fromMajor(199.999)->amount)->toBe(20000);
    expect(Money::fromMajor(0.005)->amount)->toBe(1);
});

it('adds and subtracts without mutating the operands', function (): void {
    $a = Money::fromMinor(1000);
    $b = Money::fromMinor(250);

    expect($a->add($b)->amount)->toBe(1250)
        ->and($a->subtract($b)->amount)->toBe(750)
        // Immutability: the originals are untouched.
        ->and($a->amount)->toBe(1000)
        ->and($b->amount)->toBe(250);
});

it('multiplies with half-up rounding', function (): void {
    expect(Money::fromMinor(999)->multiply(3)->amount)->toBe(2997);
    expect(Money::fromMinor(101)->multiply(0.5)->amount)->toBe(51); // 50.5 -> 51
});

it('compares amounts', function (): void {
    $low = Money::fromMinor(100);
    $high = Money::fromMinor(200);

    expect($low->lessThan($high))->toBeTrue()
        ->and($high->greaterThan($low))->toBeTrue()
        ->and($low->equals(Money::fromMinor(100)))->toBeTrue()
        ->and($low->equals($high))->toBeFalse();
});

it('reports sign correctly', function (): void {
    expect(Money::zero()->isZero())->toBeTrue()
        ->and(Money::fromMinor(1)->isPositive())->toBeTrue()
        ->and(Money::fromMinor(-1)->isNegative())->toBeTrue();
});

it('formats for display with thousands separators', function (): void {
    expect(Money::fromMinor(149900)->format())->toBe('1,499.00');
    expect(Money::fromMinor(100000000)->format())->toBe('1,000,000.00');
    expect((string) Money::fromMinor(149900))->toBe('INR 1,499.00');
});

it('serialises to json with amount, currency and formatted value', function (): void {
    expect(Money::fromMinor(149900)->jsonSerialize())->toBe([
        'amount' => 149900,
        'currency' => 'INR',
        'formatted' => '1,499.00',
    ]);
});

it('refuses arithmetic across different currencies', function (): void {
    // Guessing an exchange rate silently would be far worse than failing.
    // Only INR is supported in V1, so this is proven via the constructor guard.
    expect(fn () => Money::fromMinor(100, 'USD'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported currency [USD]');

    expect(fn () => Money::fromMajor(1, 'EUR'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported currency [EUR]');
});

it('normalises currency codes to upper case', function (): void {
    expect(Money::fromMinor(100, 'inr')->currency)->toBe('INR');
});
