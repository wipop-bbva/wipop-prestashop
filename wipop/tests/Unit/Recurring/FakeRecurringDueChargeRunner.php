<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Recurring;

use PrestaShop\Module\Wipop\Recurring\RecurringDueChargeRunnerInterface;
use RuntimeException;

final class FakeRecurringDueChargeRunner implements RecurringDueChargeRunnerInterface
{
    public int $calls = 0;
    public ?int $lastLimit = null;
    public bool $attemptWasMarkedBeforeRun = false;
    public bool $throws = false;

    public function __construct(private FakeRecurringAutoRunnerState $state)
    {
    }

    public function runDue(int $limit): int
    {
        ++$this->calls;
        $this->lastLimit = $limit;
        $this->attemptWasMarkedBeforeRun = $this->state->lastAttemptAt() !== null;

        if ($this->throws) {
            throw new RuntimeException('Runner failed.');
        }

        return 0;
    }
}
