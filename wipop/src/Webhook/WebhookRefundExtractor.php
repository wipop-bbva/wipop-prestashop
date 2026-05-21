<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Webhook;

use PrestaShop\Module\Wipop\Exception\WebhookException;
use PrestaShop\Module\Wipop\Payment\PaymentEventData;
use PrestaShop\Module\Wipop\Payment\TransactionType;
use Wipop\Domain\Transaction;

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
            return $this->fromTransaction($transaction);
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
    private function fromTransaction(Transaction $transaction): PaymentEventData
    {
        $transactionId = $this->nullableString($transaction->id);
        if ($transactionId === null) {
            throw new WebhookException('Missing refund transaction id.', 400);
        }

        return new PaymentEventData(
            $transactionId,
            null,
            $this->nullableString($transaction->orderId),
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
