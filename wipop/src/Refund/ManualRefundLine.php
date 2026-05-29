<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

use PrestaShop\Module\Wipop\Payment\PaymentAmount;

final class ManualRefundLine
{
    public function __construct(
        public readonly int $orderDetailId,
        public readonly int $quantity,
        public readonly float $amount,
    ) {
    }

    public function amountCents(): int
    {
        return PaymentAmount::toCents($this->amount);
    }

    public function amountForCommand(): string
    {
        return self::formatCents($this->amountCents());
    }

    public static function formatCents(int $amountCents): string
    {
        return number_format(PaymentAmount::fromCents($amountCents), 2, '.', '');
    }
}
