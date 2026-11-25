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

use DateTimeImmutable;
use Db;
use RuntimeException;

use function pSQL;
use function sprintf;

if (!defined('_PS_VERSION_')) {
    exit;
}

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
