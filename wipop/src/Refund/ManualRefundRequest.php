<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

use PrestaShop\Module\Wipop\Payment\PaymentAmount;

final class ManualRefundRequest
{
    /**
     * @param ManualRefundLine[] $lines
     */
    public function __construct(
        private array $lines,
        public readonly float $shippingAmount,
        public readonly bool $restock,
    ) {
    }

    /**
     * @return ManualRefundLine[]
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function shippingCents(): int
    {
        return PaymentAmount::toCents($this->shippingAmount);
    }

    public function totalCents(): int
    {
        $total = $this->shippingCents();
        foreach ($this->lines as $line) {
            $total += $line->amountCents();
        }

        return $total;
    }

    public function shippingAmountForCommand(): string
    {
        return ManualRefundLine::formatCents($this->shippingCents());
    }

    /**
     * @return array<int, array{quantity: int, amount: string}>
     */
    public function orderDetailRefundsForCommand(): array
    {
        $refunds = [];
        foreach ($this->lines as $line) {
            $refunds[$line->orderDetailId] = [
                'quantity' => $line->quantity,
                'amount' => $line->amountForCommand(),
            ];
        }

        return $refunds;
    }
}
