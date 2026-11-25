<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Card;

use PrestaShop\Module\Wipop\Card\StoredCard;
use PrestaShop\Module\Wipop\Card\StoredCardProviderInterface;

final class InMemoryStoredCards implements StoredCardProviderInterface
{
    /**
     * @param StoredCard[] $cards
     */
    public function __construct(private array $cards)
    {
    }

    public function findActiveByIdForCustomer(
        int $cardId,
        int $customerId,
        int $shopId,
        string $merchantId,
        bool $isLive,
        int $terminalId,
    ): ?StoredCard {
        foreach ($this->cards as $card) {
            if (
                $card->id === $cardId
                && $card->customerId === $customerId
                && $card->shopId === $shopId
                && $card->merchantId === $merchantId
                && $card->isLive === $isLive
                && $card->terminalId === $terminalId
            ) {
                return $card;
            }
        }

        return null;
    }
}
