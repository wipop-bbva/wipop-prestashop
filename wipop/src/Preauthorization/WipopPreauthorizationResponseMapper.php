<?php

declare(strict_types=1);

namespace PrestaShop\Module\Wipop\Preauthorization;

use Wipop\Domain\Charge;

final class WipopPreauthorizationResponseMapper
{
    /**
     * @throws PreauthorizationException
     */
    public function map(string $parentTransactionId, Charge $charge): PreauthorizationResult
    {
        $remoteTransactionId = $this->nullableString($charge->id);
        if ($remoteTransactionId === null) {
            throw new PreauthorizationException('Wipop did not return a transaction identifier.');
        }

        $status = $charge->status?->value;
        if ($status === null) {
            throw new PreauthorizationException('Wipop did not return an operation status.');
        }

        if ($charge->amount === null) {
            throw new PreauthorizationException('Wipop did not return an operation amount.');
        }

        $currencyIso = $this->nullableString($charge->currency);
        if ($currencyIso === null) {
            throw new PreauthorizationException('Wipop did not return an operation currency.');
        }

        return new PreauthorizationResult(
            $remoteTransactionId,
            $parentTransactionId,
            $this->nullableString($charge->orderId),
            $status,
            $charge->amount,
            $currencyIso,
            $this->nullableString($charge->operationType),
            $this->nullableString($charge->errorCode),
            $this->nullableString($charge->errorMessage),
        );
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
