<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Refund;

use Wipop\Domain\Charge;

final class WipopRefundResponseMapper
{
    /**
     * @throws ManualRefundException
     */
    public function map(string $parentTransactionId, Charge $charge): WipopRefundResult
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

        $amount = $refund->amount;
        if ($amount === null) {
            throw new ManualRefundException('Wipop did not return a refund amount.');
        }

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
