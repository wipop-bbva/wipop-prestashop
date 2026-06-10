<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

final class RecurringCartGroup
{
    /**
     * @param RecurringCartItem[] $items
     */
    public function __construct(
        public readonly RecurringPeriod $period,
        public readonly float $amount,
        public readonly array $items,
    ) {
    }
}
