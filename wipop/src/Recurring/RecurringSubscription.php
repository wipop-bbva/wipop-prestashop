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

if (!defined('_PS_VERSION_')) {
    exit;
}

final class RecurringSubscription
{
    /**
     * @param RecurringSubscriptionItem[] $items
     */
    public function __construct(
        public readonly int $id,
        public readonly int $initialPaymentAttemptId,
        public readonly ?int $parentOrderId,
        public readonly int $customerId,
        public readonly int $shopId,
        public readonly string $merchantId,
        public readonly bool $isLive,
        public readonly int $terminalId,
        public readonly int $currencyId,
        public readonly int $languageId,
        public readonly int $invoiceAddressId,
        public readonly int $deliveryAddressId,
        public readonly ?int $storedCardId,
        public readonly RecurringPeriod $period,
        public readonly RecurringSubscriptionStatus $status,
        public readonly float $amount,
        public readonly string $currencyIso,
        public readonly int $nextSequence,
        public readonly ?string $nextAttemptAt,
        public readonly ?string $lastTransactionId,
        public readonly array $items = [],
    ) {
    }
}
