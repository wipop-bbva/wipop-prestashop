<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

use Db;

final class ManualRefundLock
{
    private const LOCK_TIMEOUT_SECONDS = 5;

    private Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? Db::getInstance();
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     *
     * @throws ManualRefundException
     */
    public function run(int $attemptId, callable $callback): mixed
    {
        $lockName = $this->lockName($attemptId);
        if (!$this->acquire($lockName)) {
            throw new ManualRefundException('A Wipop refund is already being processed for this order.');
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
        return sprintf('wipop_manual_refund_%d', $attemptId);
    }

    private function sqlString(string $value): string
    {
        return sprintf('\'%s\'', pSQL($value));
    }
}
