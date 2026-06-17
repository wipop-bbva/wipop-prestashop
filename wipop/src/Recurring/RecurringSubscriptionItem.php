<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

final class RecurringSubscriptionItem
{
    public function __construct(
        public readonly int $productId,
        public readonly int $productAttributeId,
        public readonly int $quantity,
    ) {
    }
}
