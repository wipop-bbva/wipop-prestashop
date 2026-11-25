<?php
/**
 * 2007-2026 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2026 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use PrestaShop\Module\Wipop\Log\LoggerInterface;
use Throwable;

if (!defined('_PS_VERSION_')) {
    exit;
}

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
