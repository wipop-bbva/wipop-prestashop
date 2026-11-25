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

if (!defined('_PS_VERSION_')) {
    exit;
}

interface RefundEventStoreInterface
{
    public function saveManualRefund(PaymentAttempt $attempt, PaymentEventData $event, int $employeeId): void;

    public function linkOrderSlip(string $remoteTransactionId, int $orderSlipId): void;

    public function completedRefundedCentsForAttempt(
        int $attemptId,
        string $currencyIso,
        ?string $excludedRemoteTransactionId = null,
    ): int;

    public function findLatestCompletedCaptureTransactionId(int $attemptId): ?string;
}
