<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use PrestaShop\Module\Wipop\Log\LoggerInterface;
use Throwable;

final class RecurringAutoRunner
{
    private const LOCK_NAME = 'wipop_recurring_auto_runner';
    private const LOCK_TIMEOUT_SECONDS = 0;
    private const MIN_INTERVAL_SECONDS = 900;
    private const LIMIT = 1;

    public function __construct(
        private RecurringDueChargeRunnerInterface $runner,
        private RecurringAutoRunnerStateInterface $state,
        private RecurringNamedLockInterface $lock,
        private LoggerInterface $logger,
    ) {
    }

    public function maybeRun(): void
    {
        try {
            if (!$this->isDue(time())) {
                return;
            }

            $this->lock->run(
                self::LOCK_NAME,
                self::LOCK_TIMEOUT_SECONDS,
                function (): void {
                    $now = time();
                    if (!$this->isDue($now)) {
                        return;
                    }

                    $this->state->markAttemptAt($now);
                    $this->runner->runDue(self::LIMIT);
                }
            );
        } catch (RecurringLockUnavailableException) {
            return;
        } catch (Throwable $throwable) {
            $this->logger->error('Unable to run Wipop recurring auto-runner.', [
                'error_class' => $throwable::class,
            ]);
        }
    }

    private function isDue(int $now): bool
    {
        $lastAttemptAt = $this->state->lastAttemptAt();

        return $lastAttemptAt === null || $lastAttemptAt + self::MIN_INTERVAL_SECONDS <= $now;
    }
}
