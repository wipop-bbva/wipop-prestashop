<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Recurring;

use DateTimeImmutable;
use Db;
use RuntimeException;

use function pSQL;
use function sprintf;

final class ProductRecurringSettingsRepository
{
    public function __construct(private Db $db)
    {
    }

    public static function createDefault(): self
    {
        return new self(Db::getInstance());
    }

    public function findPeriod(int $productId, int $shopId): ?RecurringPeriod
    {
        if ($productId <= 0 || $shopId <= 0) {
            return null;
        }

        $sql = sprintf(
            'SELECT `period`
            FROM `%swipop_recurring_product`
            WHERE `id_product` = %d
              AND `id_shop` = %d',
            _DB_PREFIX_,
            $productId,
            $shopId
        );

        return RecurringPeriod::fromValue($this->db->getValue($sql));
    }

    public function save(int $productId, int $shopId, ?RecurringPeriod $period): void
    {
        if ($productId <= 0 || $shopId <= 0) {
            return;
        }

        if ($period === null) {
            $this->delete($productId, $shopId);

            return;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $sql = sprintf(
            'INSERT INTO `%swipop_recurring_product` (
                `id_product`,
                `id_shop`,
                `period`,
                `date_add`,
                `date_upd`
            ) VALUES (
                %d,
                %d,
                \'%s\',
                \'%s\',
                \'%s\'
            ) ON DUPLICATE KEY UPDATE
                `period` = VALUES(`period`),
                `date_upd` = VALUES(`date_upd`)',
            _DB_PREFIX_,
            $productId,
            $shopId,
            pSQL($period->value),
            pSQL($now),
            pSQL($now)
        );

        if (!$this->db->execute($sql)) {
            throw new RuntimeException('Unable to save Wipop recurring product settings.');
        }
    }

    public function delete(int $productId, int $shopId): void
    {
        if ($productId <= 0 || $shopId <= 0) {
            return;
        }

        $where = sprintf('`id_product` = %d AND `id_shop` = %d', $productId, $shopId);
        if (!$this->db->delete('wipop_recurring_product', $where)) {
            throw new RuntimeException('Unable to delete Wipop recurring product settings.');
        }
    }
}
