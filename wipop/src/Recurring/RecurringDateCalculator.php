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

if (!defined('_PS_VERSION_')) {
    exit;
}

final class RecurringDateCalculator
{
    public function next(DateTimeImmutable $anchor, RecurringPeriod $period): DateTimeImmutable
    {
        return match ($period) {
            RecurringPeriod::MONTHLY => $this->addMonths($anchor, 1),
            RecurringPeriod::YEARLY => $this->addMonths($anchor, 12),
        };
    }

    private function addMonths(DateTimeImmutable $anchor, int $months): DateTimeImmutable
    {
        $month = (int) $anchor->format('n') + $months;
        $year = (int) $anchor->format('Y') + intdiv($month - 1, 12);
        $month = (($month - 1) % 12) + 1;
        $lastDay = (int) $anchor->setDate($year, $month, 1)->modify('last day of this month')->format('j');
        $day = min((int) $anchor->format('j'), $lastDay);

        return $anchor->setDate($year, $month, $day);
    }
}
