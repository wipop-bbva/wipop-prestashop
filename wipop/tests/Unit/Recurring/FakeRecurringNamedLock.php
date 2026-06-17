<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Recurring;

use Closure;
use PrestaShop\Module\Wipop\Recurring\RecurringLockUnavailableException;
use PrestaShop\Module\Wipop\Recurring\RecurringNamedLockInterface;

final class FakeRecurringNamedLock implements RecurringNamedLockInterface
{
    public int $calls = 0;
    public bool $available = true;
    public ?Closure $beforeCallback = null;

    public function run(string $lockName, int $timeoutSeconds, callable $callback): mixed
    {
        ++$this->calls;

        if (!$this->available) {
            throw new RecurringLockUnavailableException('Locked.');
        }

        if ($this->beforeCallback !== null) {
            ($this->beforeCallback)();
        }

        return $callback();
    }
}
