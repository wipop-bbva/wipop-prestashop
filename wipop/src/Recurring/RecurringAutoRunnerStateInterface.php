<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

interface RecurringAutoRunnerStateInterface
{
    public function lastAttemptAt(): ?int;

    public function markAttemptAt(int $timestamp): void;
}
