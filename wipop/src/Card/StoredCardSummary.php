<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Card;

final class StoredCardSummary
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $brand,
        public readonly ?string $last4,
        public readonly ?string $expirationMonth,
        public readonly ?string $expirationYear,
    ) {
    }
}
