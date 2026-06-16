<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

interface RecurringDueChargeRunnerInterface
{
    public function runDue(int $limit): int;
}
