<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use Db;
use RuntimeException;

final class RecurringSubscriptionLock
{
    private const LOCK_TIMEOUT_SECONDS = 5;

    public function __construct(private ?Db $db = null)
    {
        $this->db ??= Db::getInstance();
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function run(int $subscriptionId, callable $callback): mixed
    {
        $lockName = sprintf('wipop_recurring_subscription_%d', $subscriptionId);
        if (!$this->acquire($lockName)) {
            throw new RuntimeException('Wipop recurring subscription is locked.');
        }

        try {
            return $callback();
        } finally {
            $this->release($lockName);
        }
    }

    private function acquire(string $lockName): bool
    {
        $sql = sprintf('SELECT GET_LOCK(\'%s\', %d)', pSQL($lockName), self::LOCK_TIMEOUT_SECONDS);

        return (int) $this->db->getValue($sql) === 1;
    }

    private function release(string $lockName): void
    {
        $this->db->getValue(sprintf('SELECT RELEASE_LOCK(\'%s\')', pSQL($lockName)));
    }
}
