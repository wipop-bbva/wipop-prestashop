<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

final class ManualRefundOutcome
{
    public function __construct(
        public readonly string $refundTransactionId,
        public readonly float $amount,
        public readonly string $currencyIso,
        public readonly ?int $orderSlipId,
        public readonly ?string $localWarning,
    ) {
    }
}
