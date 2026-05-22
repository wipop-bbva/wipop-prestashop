<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Payment\PaymentAmount;

final class PaymentAmountTest extends TestCase
{
    public function testItRoundsAmountsToNearestCent(): void
    {
        self::assertSame(610, PaymentAmount::toCents(6.1));
        self::assertSame(1999, PaymentAmount::toCents(19.99));
        self::assertSame(1001, PaymentAmount::toCents(10.005));
    }

    public function testItComparesAmountsByExactCents(): void
    {
        self::assertTrue(PaymentAmount::matches(10.0, 10.00));
        self::assertFalse(PaymentAmount::matches(10.0, 9.99));
        self::assertTrue(PaymentAmount::matches(10.0, 10.004));
        self::assertFalse(PaymentAmount::matches(10.0, 10.005));
    }
}
