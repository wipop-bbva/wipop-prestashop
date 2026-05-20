<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

final class PaymentAttemptSummary
{
    public function __construct(
        public readonly int $id,
        public readonly int $cartId,
        public readonly ?string $paymentMethod,
        public readonly PaymentAttemptStatus $status,
        public readonly float $amount,
        public readonly string $currencyIso,
        public readonly ?string $wipopOrderId,
        public readonly ?string $wipopTransactionId,
        public readonly ?string $remoteStatus,
        public readonly ?string $remoteErrorCode,
        public readonly ?string $remoteErrorMessage,
        public readonly ?string $webhookReceivedAt,
    ) {
    }
}
