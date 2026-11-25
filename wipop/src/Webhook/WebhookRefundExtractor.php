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

namespace PrestaShop\Module\Wipop\Webhook;

use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use PrestaShop\Module\Wipop\Payment\TransactionType;
use Wipop\Domain\Transaction;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class WebhookRefundExtractor
{
    /**
     * @param array<string, mixed> $payload
     *
     * @throws WebhookException
     */
    public function extract(Transaction $transaction, array $payload): ?PaymentEventData
    {
        if (TransactionType::isRefund($transaction->transactionType)) {
            return $this->fromTransaction($transaction, $payload['charge'] ?? null);
        }

        $refundPayload = $payload['refund'] ?? null;
        if (!is_array($refundPayload)) {
            return null;
        }

        return $this->fromRefundPayload($transaction, $refundPayload);
    }

    /**
     * @throws WebhookException
     */
    private function fromTransaction(Transaction $transaction, mixed $chargePayload): PaymentEventData
    {
        $transactionId = $this->nullableString($transaction->id);
        if ($transactionId === null) {
            throw new WebhookException('Missing refund transaction id.', 400);
        }

        $charge = is_array($chargePayload) ? $chargePayload : [];

        return new PaymentEventData(
            $transactionId,
            $this->nullableString($charge['id'] ?? $charge['public_id'] ?? null),
            $this->nullableString($charge['order_id'] ?? $transaction->orderId),
            PaymentEventData::TYPE_REFUND,
            $this->nullableString($transaction->operationType),
            $transaction->status?->value,
            $transaction->amount,
            $this->nullableString($transaction->currency),
            $this->nullableString($transaction->errorCode),
            $this->nullableString($transaction->errorMessage),
        );
    }

    /**
     * @param array<string, mixed> $refundPayload
     *
     * @throws WebhookException
     */
    private function fromRefundPayload(Transaction $transaction, array $refundPayload): PaymentEventData
    {
        $transactionId = $this->nullableString($refundPayload['public_id'] ?? $refundPayload['id'] ?? null);
        if ($transactionId === null) {
            throw new WebhookException('Missing refund transaction id.', 400);
        }

        return new PaymentEventData(
            $transactionId,
            $this->nullableString($transaction->id),
            $this->nullableString($refundPayload['order_id'] ?? $transaction->orderId),
            PaymentEventData::TYPE_REFUND,
            $this->nullableString($transaction->operationType),
            $this->nullableString($refundPayload['status'] ?? null),
            $this->nullableFloat($refundPayload['amount'] ?? null),
            $this->nullableString($refundPayload['currency'] ?? null),
            $this->nullableString($transaction->errorCode),
            $this->nullableString($transaction->errorMessage),
        );
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
