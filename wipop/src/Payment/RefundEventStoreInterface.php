<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

interface RefundEventStoreInterface
{
    public function saveManualRefund(PaymentAttempt $attempt, PaymentEventData $event, int $employeeId): void;

    public function linkOrderSlip(string $remoteTransactionId, int $orderSlipId): void;

    public function completedRefundedCentsForAttempt(
        int $attemptId,
        string $currencyIso,
        ?string $excludedRemoteTransactionId = null,
    ): int;
}
