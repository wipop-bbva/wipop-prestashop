<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

interface RecurringNamedLockInterface
{
    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function run(string $lockName, int $timeoutSeconds, callable $callback): mixed;
}
