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

namespace PrestaShop\Module\Wipop\Payment;

use Db;
use RuntimeException;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PaymentAttemptLock
{
    private const LOCK_TIMEOUT_SECONDS = 5;

    private Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? Db::getInstance();
    }

    /**
     * Shared per attempt across refunds, captures and reversals to avoid concurrent remote money operations.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     *
     * @throws PaymentAttemptLockException
     * @throws RuntimeException
     */
    public function run(int $attemptId, callable $callback): mixed
    {
        $lockName = $this->lockName($attemptId);
        if (!$this->acquire($lockName)) {
            throw new PaymentAttemptLockException();
        }

        try {
            return $callback();
        } finally {
            $this->release($lockName);
        }
    }

    private function acquire(string $lockName): bool
    {
        $sql = sprintf(
            'SELECT GET_LOCK(%s, %d)',
            $this->sqlString($lockName),
            self::LOCK_TIMEOUT_SECONDS
        );

        $result = (string) $this->db->getValue($sql);
        if ($result === '1') {
            return true;
        }

        if ($result === '0') {
            return false;
        }

        throw new RuntimeException('Unable to acquire Wipop payment attempt lock.');
    }

    private function release(string $lockName): void
    {
        $this->db->getValue(sprintf('SELECT RELEASE_LOCK(%s)', $this->sqlString($lockName)));
    }

    private function lockName(int $attemptId): string
    {
        return sprintf('wipop_payment_attempt_%d', $attemptId);
    }

    private function sqlString(string $value): string
    {
        return sprintf('\'%s\'', pSQL($value));
    }
}
