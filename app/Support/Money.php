<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An immutable amount of money, stored as an integer number of minor units.
 *
 * WHY THIS EXISTS (ADR-007, FR-CRS-09):
 * Floating point cannot represent decimal currency exactly. `0.1 + 0.2 !== 0.3`
 * in binary floating point, and an LMS that mis-totals a payment by a paisa is
 * a reconciliation problem, not a rounding curiosity. Every monetary value in
 * this system is therefore an integer count of the currency's smallest unit —
 * paise for INR — and never a float or a decimal string.
 *
 * This also matches Razorpay's API, which accepts and returns amounts in paise
 * (architecture.md §11), so no conversion happens at the gateway boundary.
 *
 * INVARIANTS
 *  - The amount is always an integer in minor units.
 *  - Currency is an ISO 4217 alpha-3 code, upper-cased.
 *  - Arithmetic between different currencies throws rather than guessing.
 *  - Instances are immutable; every operation returns a new instance.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    /**
     * Number of minor units per major unit, by currency.
     *
     * Most currencies are 100:1, but not all (JPY is 1:1, KWD is 1000:1), so
     * this is a lookup rather than a hard-coded 100. V1 ships INR only
     * (constraint C-07); entries are added as currencies are supported.
     *
     * @var array<string, int>
     */
    private const MINOR_UNITS = [
        'INR' => 100,
    ];

    /**
     * @param  int  $amount  Amount in the currency's minor unit (e.g. paise).
     * @param  string  $currency  ISO 4217 alpha-3 code.
     */
    public function __construct(
        public int $amount,
        public string $currency = 'INR',
    ) {
        if (! array_key_exists($this->currency, self::MINOR_UNITS)) {
            throw new InvalidArgumentException(
                "Unsupported currency [{$this->currency}]. Supported: ".implode(', ', array_keys(self::MINOR_UNITS)).'.',
            );
        }
    }

    /**
     * Build from an integer number of minor units — the canonical constructor,
     * and the one that should be used when reading from the database.
     */
    public static function fromMinor(int $amount, string $currency = 'INR'): self
    {
        return new self($amount, strtoupper($currency));
    }

    /**
     * Build from a major-unit value (e.g. rupees) entered by an administrator.
     *
     * Accepts int|float|string. A float is rounded to the nearest minor unit
     * rather than truncated, so 199.999 becomes 20000 paise, not 19999. Prefer
     * passing a string for exactness when the value came from user input.
     */
    public static function fromMajor(int|float|string $amount, string $currency = 'INR'): self
    {
        $currency = strtoupper($currency);
        $factor = self::MINOR_UNITS[$currency]
            ?? throw new InvalidArgumentException("Unsupported currency [{$currency}].");

        return new self((int) round(((float) $amount) * $factor), $currency);
    }

    public static function zero(string $currency = 'INR'): self
    {
        return new self(0, strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    /**
     * Multiply by a scalar, rounding half-up to the nearest minor unit.
     */
    public function multiply(int|float $factor): self
    {
        return new self((int) round($this->amount * $factor), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    /**
     * The value in major units. For DISPLAY and calculation-free use only —
     * never persist this, and never compute with it.
     */
    public function toMajor(): float
    {
        return $this->amount / self::MINOR_UNITS[$this->currency];
    }

    /**
     * Human-readable amount, e.g. "1,499.00". No currency symbol: symbol and
     * placement are presentation concerns resolved in the view layer.
     */
    public function format(): string
    {
        $factor = self::MINOR_UNITS[$this->currency];
        $decimals = (int) round(log10($factor));

        return number_format($this->toMajor(), $decimals, '.', ',');
    }

    public function __toString(): string
    {
        return $this->currency.' '.$this->format();
    }

    /**
     * @return array{amount: int, currency: string, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on different currencies [{$this->currency}] and [{$other->currency}].",
            );
        }
    }
}
