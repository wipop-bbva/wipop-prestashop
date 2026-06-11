<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

final class RecurringSubscription
{
    /**
     * @param RecurringSubscriptionItem[] $items
     */
    public function __construct(
        public readonly int $id,
        public readonly int $initialPaymentAttemptId,
        public readonly ?int $parentOrderId,
        public readonly int $customerId,
        public readonly int $shopId,
        public readonly string $merchantId,
        public readonly bool $isLive,
        public readonly int $terminalId,
        public readonly int $currencyId,
        public readonly int $languageId,
        public readonly int $invoiceAddressId,
        public readonly int $deliveryAddressId,
        public readonly ?int $storedCardId,
        public readonly RecurringPeriod $period,
        public readonly RecurringSubscriptionStatus $status,
        public readonly float $amount,
        public readonly string $currencyIso,
        public readonly int $nextSequence,
        public readonly ?string $nextAttemptAt,
        public readonly ?string $lastTransactionId,
        public readonly array $items = [],
    ) {
    }
}
