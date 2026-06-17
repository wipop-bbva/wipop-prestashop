<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

final class RecurringSubscriptionLock
{
    private const LOCK_TIMEOUT_SECONDS = 5;

    public function __construct(private RecurringNamedLockInterface $lock)
    {
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function run(int $subscriptionId, callable $callback): mixed
    {
        $lockName = sprintf('wipop_recurring_subscription_%d', $subscriptionId);

        return $this->lock->run($lockName, self::LOCK_TIMEOUT_SECONDS, $callback);
    }
}
