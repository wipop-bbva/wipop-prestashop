<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use Db;
use RuntimeException;

final class MySqlRecurringNamedLock implements RecurringNamedLockInterface
{
    public function __construct(private ?Db $db = null)
    {
        $this->db ??= Db::getInstance();
    }

    public function run(string $lockName, int $timeoutSeconds, callable $callback): mixed
    {
        if (!$this->acquire($lockName, $timeoutSeconds)) {
            throw new RecurringLockUnavailableException('Wipop recurring lock is already held.');
        }

        try {
            return $callback();
        } finally {
            $this->release($lockName);
        }
    }

    private function acquire(string $lockName, int $timeoutSeconds): bool
    {
        $sql = sprintf('SELECT GET_LOCK(\'%s\', %d)', pSQL($lockName), $timeoutSeconds);
        $result = $this->db->getValue($sql);

        if ($result === '1') {
            return true;
        }

        if ($result === '0') {
            return false;
        }

        throw new RuntimeException('Unable to acquire Wipop recurring lock.');
    }

    private function release(string $lockName): void
    {
        $this->db->getValue(sprintf('SELECT RELEASE_LOCK(\'%s\')', pSQL($lockName)));
    }
}
