<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

final class RecurringCartSummary
{
    /**
     * @param RecurringCartGroup[] $groups
     */
    public function __construct(
        public readonly array $groups,
        public readonly bool $isVirtualCart,
    ) {
    }

    public function hasRecurringProducts(): bool
    {
        return $this->groups !== [];
    }
}
