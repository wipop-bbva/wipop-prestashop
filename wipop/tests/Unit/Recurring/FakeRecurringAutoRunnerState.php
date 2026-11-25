<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Tests\Unit\Recurring;

use PrestaShop\Module\Wipop\Recurring\RecurringAutoRunnerStateInterface;

final class FakeRecurringAutoRunnerState implements RecurringAutoRunnerStateInterface
{
    public int $markCalls = 0;

    public function __construct(public ?int $lastAttemptAt)
    {
    }

    public function lastAttemptAt(): ?int
    {
        return $this->lastAttemptAt;
    }

    public function markAttemptAt(int $timestamp): void
    {
        ++$this->markCalls;
        $this->lastAttemptAt = $timestamp;
    }
}
