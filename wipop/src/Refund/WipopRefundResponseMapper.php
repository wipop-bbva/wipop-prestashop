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

namespace PrestaShop\Module\Wipop\Refund;

use Wipop\Domain\Charge;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class WipopRefundResponseMapper
{
    /**
     * @throws ManualRefundException
     */
    public function map(string $parentTransactionId, Charge $charge, ?float $requestedAmount = null): WipopRefundResult
    {
        $refund = $charge->refund;
        if ($refund === null) {
            throw new ManualRefundException('Wipop did not return refund details.');
        }

        $refundTransactionId = $this->nullableString($refund->id) ?? $this->nullableString($charge->id);
        if ($refundTransactionId === null) {
            throw new ManualRefundException('Wipop did not return a refund transaction identifier.');
        }

        if ($refundTransactionId === $parentTransactionId) {
            throw new ManualRefundException('Wipop returned the original transaction as refund identifier.');
        }

        $status = $refund->status?->value;
        if ($status === null) {
            throw new ManualRefundException('Wipop did not return a refund status.');
        }

        if ($refund->amount === null) {
            throw new ManualRefundException('Wipop did not return a refund amount.');
        }
        $amount = $requestedAmount ?? $refund->amount;

        $currencyIso = $this->nullableString($refund->currency);
        if ($currencyIso === null) {
            throw new ManualRefundException('Wipop did not return a refund currency.');
        }

        return new WipopRefundResult(
            $refundTransactionId,
            $parentTransactionId,
            $this->nullableString($refund->orderId) ?? $this->nullableString($charge->orderId),
            $status,
            $amount,
            $currencyIso,
            $this->nullableString($refund->operationType) ?? $this->nullableString($charge->operationType),
            $this->nullableString($refund->errorCode) ?? $this->nullableString($charge->errorCode),
            $this->nullableString($refund->errorMessage) ?? $this->nullableString($charge->errorMessage),
        );
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
