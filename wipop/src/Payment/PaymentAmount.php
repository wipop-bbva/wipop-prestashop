<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

final class PaymentAmount
{
    private const MINOR_UNITS = 100;

    public static function toCents(float $amount): int
    {
        return (int) round($amount * self::MINOR_UNITS);
    }

    public static function fromCents(int $amount): float
    {
        return $amount / self::MINOR_UNITS;
    }

    public static function matches(float $expected, float $actual): bool
    {
        return self::toCents($expected) === self::toCents($actual);
    }
}
