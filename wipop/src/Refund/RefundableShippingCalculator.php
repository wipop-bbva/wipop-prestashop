<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

final class RefundableShippingCalculator
{
    /**
     * @param array<int, array<string, mixed>> $orderSlips
     */
    public function remainingTaxIncludedAmount(float $orderShippingTaxIncluded, array $orderSlips): float
    {
        $amount = $orderShippingTaxIncluded;

        foreach ($orderSlips as $slip) {
            $amount -= (float) ($slip['total_shipping_tax_incl'] ?? 0);
        }

        return max(0.0, $amount);
    }
}
