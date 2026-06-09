<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Card;

interface StoredCardProviderInterface
{
    public function findActiveByIdForCustomer(
        int $cardId,
        int $customerId,
        int $shopId,
        string $merchantId,
        bool $isLive,
        int $terminalId,
    ): ?StoredCard;
}
