<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Card;

final class StoredCard
{
    public function __construct(
        public readonly int $id,
        public readonly int $customerId,
        public readonly int $shopId,
        public readonly string $merchantId,
        public readonly bool $isLive,
        public readonly int $terminalId,
        public readonly string $sourceId,
        public readonly ?string $wipopCustomerId,
        public readonly ?string $brand,
        public readonly ?string $last4,
        public readonly ?string $expirationMonth,
        public readonly ?string $expirationYear,
    ) {
    }
}
