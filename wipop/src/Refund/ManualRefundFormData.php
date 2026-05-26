<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

use PrestaShop\Module\Wipop\Payment\PaymentAmount;

final class ManualRefundFormData
{
    /**
     * @param ManualRefundFormLine[] $lines
     */
    public function __construct(
        public readonly array $lines,
        public readonly float $shippingRefundableAmount,
        public readonly float $shippingRefundableAmountTaxIncluded,
        public readonly string $shippingRefundableAmountFormatted,
        public readonly string $currencyIso,
        public readonly string $taxMethod,
        public readonly bool $isTaxIncluded,
    ) {
    }

    public function shippingRefundableCents(): int
    {
        return PaymentAmount::toCents($this->shippingRefundableAmount);
    }

    public function shippingTaxIncludedAmountCentsFor(int $shippingCents): int
    {
        if ($this->isTaxIncluded) {
            return $shippingCents;
        }

        $shippingRefundableCents = $this->shippingRefundableCents();
        if ($shippingRefundableCents <= 0) {
            return $shippingCents;
        }

        $shippingRefundableTaxIncludedCents = PaymentAmount::toCents($this->shippingRefundableAmountTaxIncluded);

        return (int) round($shippingCents * $shippingRefundableTaxIncludedCents / $shippingRefundableCents);
    }

    /**
     * @throws ManualRefundException
     */
    public function requireLineByOrderDetailId(int $orderDetailId): ManualRefundFormLine
    {
        foreach ($this->lines as $line) {
            if ($line->orderDetailId === $orderDetailId) {
                return $line;
            }
        }

        throw new ManualRefundException('The refund contains an unknown order line.');
    }
}
