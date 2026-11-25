<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Refund;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Wipop\Refund\RefundableShippingCalculator;

final class RefundableShippingCalculatorTest extends TestCase
{
    public function testItSubtractsRefundedShippingFromOrderShipping(): void
    {
        $remainingAmount = (new RefundableShippingCalculator())->remainingTaxIncludedAmount(
            12.1,
            [
                ['total_shipping_tax_incl' => '2.10'],
                ['total_shipping_tax_incl' => 3.0],
            ]
        );

        self::assertSame(7.0, $remainingAmount);
    }

    public function testItNeverReturnsNegativeShippingAmount(): void
    {
        $remainingAmount = (new RefundableShippingCalculator())->remainingTaxIncludedAmount(
            5.0,
            [
                ['total_shipping_tax_incl' => 10.0],
            ]
        );

        self::assertSame(0.0, $remainingAmount);
    }
}
