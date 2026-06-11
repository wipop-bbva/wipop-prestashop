<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

final class RecurringCharge
{
    public function __construct(
        public readonly int $id,
        public readonly int $subscriptionId,
        public readonly int $sequence,
        public readonly ?int $paymentAttemptId,
        public readonly ?int $cartId,
        public readonly ?int $orderId,
        public readonly string $wipopOrderId,
        public readonly ?string $wipopTransactionId,
        public readonly RecurringChargeStatus $status,
        public readonly ?string $remoteErrorCode,
        public readonly ?string $remoteErrorMessage,
    ) {
    }
}
