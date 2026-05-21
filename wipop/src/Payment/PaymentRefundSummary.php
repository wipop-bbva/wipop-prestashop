<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

final class PaymentRefundSummary
{
    public function __construct(
        public readonly float $refundedAmount,
        public readonly string $currencyIso,
        public readonly int $completedRefundCount,
        public readonly ?string $lastRefundTransactionId,
        public readonly ?string $lastRefundStatus,
        public readonly ?float $lastRefundAmount,
        public readonly ?string $lastRefundCurrencyIso,
        public readonly ?string $lastRefundReceivedAt,
        public readonly ?string $lastRefundErrorCode,
        public readonly ?string $lastRefundErrorMessage,
    ) {
    }
}
