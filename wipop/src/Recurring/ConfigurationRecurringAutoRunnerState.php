<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use Configuration;
use PrestaShop\Module\Wipop\Configuration\ConfigKeys;
use RuntimeException;

final class ConfigurationRecurringAutoRunnerState implements RecurringAutoRunnerStateInterface
{
    public function lastAttemptAt(): ?int
    {
        $timestamp = Configuration::get(ConfigKeys::RECURRING_AUTO_RUNNER_LAST_ATTEMPT_AT);

        return is_numeric($timestamp) && (int) $timestamp > 0 ? (int) $timestamp : null;
    }

    public function markAttemptAt(int $timestamp): void
    {
        if (!Configuration::updateValue(ConfigKeys::RECURRING_AUTO_RUNNER_LAST_ATTEMPT_AT, (string) $timestamp)) {
            throw new RuntimeException('Unable to persist Wipop recurring auto-runner attempt timestamp.');
        }
    }
}
