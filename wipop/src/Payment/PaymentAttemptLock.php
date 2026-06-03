<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Payment;

use Db;

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

        return (int) $this->db->getValue($sql) === 1;
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
