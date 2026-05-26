<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

use PrestaShop\Module\Wipop\Payment\PaymentAmount;

final class ManualRefundFormLine
{
    public function __construct(
        public readonly int $orderDetailId,
        public readonly string $name,
        public readonly string $reference,
        public readonly int $quantityRefundable,
        public readonly float $unitAmount,
        public readonly float $unitAmountTaxIncluded,
        public readonly float $amountRefundable,
        public readonly string $amountRefundableFormatted,
    ) {
    }

    public function amountRefundableForQuantityCents(int $quantity): int
    {
        $quantityAmountCents = PaymentAmount::toCents($this->unitAmount * $quantity);

        return min($quantityAmountCents, PaymentAmount::toCents($this->amountRefundable));
    }

    public function taxIncludedAmountCentsFor(int $amountCents): int
    {
        if ($this->unitAmount <= 0.0) {
            return $amountCents;
        }

        $taxIncludedAmount = PaymentAmount::fromCents($amountCents)
            * $this->unitAmountTaxIncluded
            / $this->unitAmount;

        return PaymentAmount::toCents($taxIncludedAmount);
    }
}
